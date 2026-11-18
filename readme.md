# Rabbit Client

[![CI](https://github.com/ipedis/rabbit-client/actions/workflows/ci.yml/badge.svg)](https://github.com/ipedis/rabbit-client/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)
[![PHP Version](https://img.shields.io/packagist/php-v/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)
[![License](https://img.shields.io/packagist/l/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)

RabbitMQ client library for PHP, built on the `amqp` extension. It provides:

- **events**: publish/subscribe on a topic exchange (`EventDispatcher`, `EventListener`);
- **orders**: request/reply between a manager and workers, with progress, success and error replies (`Manager`, `Worker`);
- **workflows**: groups of orders run one after the other, with progress tracking, concurrency and retry (`Workflow`, `Group`);
- **JSON schema validation** of every payload, per channel.

Everything is provided as traits: you add them to your own classes and implement a few methods (connection settings, channel factory, validator). For Symfony, [`ipedis/rabbit-client-bundle`](https://github.com/ipedis/rabbit-client-bundle) does this wiring for you.

## Installation

```bash
composer require ipedis/rabbit-client
```

Requires PHP 8.3+ with the `amqp`, `json` and `mbstring` extensions.

## Channels

Every message travels on a channel named `v<version>.<service>.<aggregate>.<action>`, for example:

```
v1.admin.publication.generate        an order
v1.admin.publication.was-exported    an event
```

`ChannelFactory` holds the protocol version and the name of your service:

```php
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;

$channelFactory = new ChannelFactory('v1', 'admin');
```

## Connection

The traits read the connection settings from methods of your class. A base class like this one is enough:

```php
use Ipedis\Rabbit\Connector;

abstract class RabbitConnection
{
    use Connector;

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $exchangeName,   // e.g. "my_events" or "my_orders"
        private string $exchangeType,   // "topic"
    ) {
    }

    public function getHost(): string { return $this->host; }
    public function getPort(): int { return $this->port; }
    public function getUser(): string { return $this->user; }
    public function getPassword(): string { return $this->password; }
    public function getExchangeName(): string { return $this->exchangeName; }
    public function getExchangeType(): string { return $this->exchangeType; }
}
```

## Publish an event

```php
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventDispatcher;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

class Dispatcher extends RabbitConnection
{
    use EventDispatcher;

    public function __construct(
        string $host, int $port, string $user, string $password, string $exchange, string $type,
        private ChannelFactory $channelFactory,
        private ValidatorInterface $validator,
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
    }

    public function getChannelFactory(): ChannelFactory { return $this->channelFactory; }
    public function getMessagePayloadValidator(): ValidatorInterface { return $this->validator; }
}

$dispatcher->dispatch(EventMessagePayload::build('v1.admin.publication.was-exported', [
    'publication' => ['sid' => 42],
]));
```

`dispatch()` throws `RabbitClientConnectException` when RabbitMQ cannot be reached and `RabbitClientPublishException` when the message cannot be published. The library does not store failed events: catch these exceptions where the event must not be lost (a Symfony Messenger handler, for example, retries on its own).

## Listen to events

```php
use Closure;
use Ipedis\Rabbit\Event\EventListener;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

class PublicationListener extends RabbitConnection
{
    use EventListener;

    // constructor and getChannelFactory() as for the dispatcher

    protected function getBindingKey(): array
    {
        return ['v1.admin.publication.*'];
    }

    // optional: one method per event
    protected function getHandledMessages(): iterable
    {
        yield 'v1.admin.publication.was-exported' => ['method' => 'onExported'];
    }

    protected function onExported(EventMessagePayload $payload): void
    {
        $sid = $payload->getData()['publication']['sid'];
        // ...
    }

    // every other subscribed event
    protected function makeMessageHandler(): Closure
    {
        return function (EventMessagePayload $payload): void {
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (\Exception $exception, ?EventMessagePayload $payload): void {
            // log it
        };
    }
}

$listener->execute(); // consumes until stopped
```

Implement `Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage` / `OnAfterMessage` to run code around each message (open and close a database connection, for example).

## Orders: manager and worker

A worker consumes one order channel and replies to the manager:

```php
use AMQPEnvelope;
use Closure;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker;

class GenerateWorker extends RabbitConnection
{
    use Worker;

    // constructor and getChannelFactory() as for the dispatcher

    protected function getQueueName(): string
    {
        return 'v1.admin.publication.generate';
    }

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $order): array {
            // optional progress reply
            $this->notifyTo($message, ReplyMessagePayload::buildFromOrderMessagePayload(
                $order, MessageHandlerInterface::TYPE_PROGRESS, ['step' => 1]
            ));

            return ['done' => true]; // sent back with the success reply
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        // the returned array is added as context to the error reply
        return fn (\Exception $exception, ?OrderMessagePayload $order): array => [];
    }
}

$worker->execute(); // consumes until stopped
```

The manager publishes orders and waits for the replies:

```php
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Manager;

class GenerateManager extends RabbitConnection
{
    use Manager;

    public function __construct(/* same arguments as the dispatcher */)
    {
        // ... same as the dispatcher, then:
        $this->connect();
        $this->resetOrdersQueue();
    }

    // getChannelFactory() and getMessagePayloadValidator() as for the dispatcher
}

$manager->bind(MessageHandlerInterface::TYPE_SUCCESS, function (ReplyMessagePayload $reply): void {
    // ...
});
$manager->publish(OrderMessagePayload::build('v1.admin.publication.generate', ['sid' => 42]));

$manager->run(); // returns when every order has replied
```

## Workflows

A workflow runs groups of orders: the orders of a group run in parallel, the next group starts when the previous one is finished. The manager is a class using `Ipedis\Rabbit\Workflow\Manager`, built like the order manager above.

```php
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Workflow;

$workflow = new Workflow(function (Group $group): void {
    $group->planifyOrder(OrderMessagePayload::build('v1.admin.publication.generate-html', ['sid' => 42]));
    $group->planifyOrder(OrderMessagePayload::build('v1.admin.publication.generate-image', ['sid' => 42]));
});
$workflow->then(function (Group $group): void {
    $group->planifyOrder(OrderMessagePayload::build('v1.admin.publication.generate-image-page', ['sid' => 42]));
});

$workflow->bind(BindableEventInterface::WORKFLOW_ON_FINISH, function () use ($workflow): void {
    echo json_encode($workflow->getProgressBag()->getWorkflowProgress());
});

$workflowManager->run($workflow);
```

## Demo

The `demo/` folder has a runnable example of every feature (events, orders, workflows with progress, retry on failure, concurrency, generators). Start a RabbitMQ on `localhost:5672` (guest/guest), then run `php demo/index.php <name>`: without a name it lists them.

## Payload validation

Each dispatcher and manager needs a `ValidatorInterface`. It receives every payload before it is published and throws `MessagePayloadInvalidSchemaException` when the data does not match the JSON schema of its channel. `demo/Utils/MessagePayloadValidator` reads one `schema.json` per channel from `demo/documents/schema/<version>/<service>/<aggregate>/<action>/`.

## Upgrading from 2.1

The recovery fallback is removed. On a RabbitMQ failure, `dispatch()` used to post the event to an HTTP "recovery" endpoint; it now throws the exception (see [Publish an event](#publish-an-event)).

- Remove `getRecoveryEventStoreEndpoint()` and the http-signature methods (`getSignatureKey()`...) from your dispatchers.
- `ipedis/http-signature` and `guzzlehttp/guzzle` are no longer dependencies: require them yourself if your code uses them.

## Local development

Requires [Docker](https://www.docker.com/).

```bash
make install   # build the container and install the dependencies
make qa        # rector + pint + phpstan + tests
```

| Command | Description |
|---------|-------------|
| `make up` / `make down` | Start / stop the container |
| `make install` / `make update` | Composer install / update |
| `make test` | Tests |
| `make phpstan` | Static analysis |
| `make pint` | Fix code style (PSR-12) |
| `make rector` | Automated refactoring |
| `make qa` | All checks |
| `make shell` | Shell in the container |

## License

Apache License 2.0, see [LICENSE](LICENSE). Maintained by [Ipedis](https://www.ipedis.com) for its Publispeak platform, provided as-is.
