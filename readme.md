# Rabbit Client

[![CI](https://github.com/ipedis/rabbit-client/actions/workflows/ci.yml/badge.svg)](https://github.com/ipedis/rabbit-client/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)
[![PHP Version](https://img.shields.io/packagist/php-v/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)
[![License](https://img.shields.io/packagist/l/ipedis/rabbit-client.svg)](https://packagist.org/packages/ipedis/rabbit-client)

RabbitMQ client library for PHP supporting event publishing, order request/reply, and workflow orchestration with progress tracking. Built on the AMQP extension.

## Installation

```bash
composer require ipedis/rabbit-client
```

> **Requires** the `ext-amqp` PHP extension.

## Quick Start

### Publish an event

```php
use Ipedis\Rabbit\Event\EventDispatcher;

class MyDispatcher
{
    use EventDispatcher;

    protected function getSignatureKey(): string
    {
        return 'your-secret';
    }
}

$dispatcher = new MyDispatcher(/* connection config */);
$payload = EventMessagePayload::build('v1.admin.publication.was-exported', [
    'publication' => ['sid' => 42],
]);
$dispatcher->dispatch($payload);
```

### Submit an order and wait for reply

```php
use Ipedis\Rabbit\Order\Manager;

class MyManager
{
    use Manager;
}

$manager = new MyManager(/* connection config */);
$payload = OrderMessagePayload::build('v1.admin.publication.generate', ['data' => '...']);

$manager->publish($payload, function (ReplyMessagePayload $reply) {
    echo $reply->getBody(); // Worker result
});

$manager->run(); // Blocks until reply received
```

## Key Concepts

| Concept | Description |
|---------|-------------|
| **Event** | Fire-and-forget pub/sub message via `EventDispatcher` / `EventListener` |
| **Order** | Request/reply pattern via `Manager` (publisher) / `Worker` (consumer) |
| **Workflow** | Sequential groups of orders with progress tracking and retry logic |
| **Channel** | Standardized naming: `v<version>.<service>.<aggregate>.<action>` |
| **Message Payload** | Typed payloads with auto-generated UUID, timestamp, and timezone |

## Channel Naming

All channels follow the pattern `v<version>.<service>.<aggregate>.<action>`:

```
v1.admin.publication.generate
v1.admin.publication.was-exported
```

## Workflow Example

```php
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

$workflow = new Workflow(function (Group $group) {
    $group->add(new Task('step-1', $payload1));
    $group->add(new Task('step-2', $payload2));
})
->then(function (Group $group) {
    $group->add(new Task('step-3', $payload3)); // Runs after group 1 completes
});

$progress = $workflow->getProgressBag();
$progress->getPercentage()->getCompleted(); // 0.0 - 100.0
$progress->getStatus();                      // pending|running|success|failed
```

## Dependencies

This library depends on [`ipedis/http-signature`](https://github.com/ipedis/http-signature) for signed HTTP recovery endpoints.

## Compatibility

| PHP | Status |
|-----|--------|
| 8.2 | ✅ |
| 8.3 | ✅ |
| 8.4 | ✅ |
| 8.5 | ✅ |

**Required extensions:** `amqp`, `json`

## Local Development

Requires [Docker](https://www.docker.com/).

```bash
make up        # Start container
make install   # Install dependencies
make qa        # Run full QA suite (rector + pint + phpstan + tests)
```

Available targets:

| Command | Description |
|---------|-------------|
| `make up` | Start container |
| `make down` | Stop container |
| `make install` | Install Composer dependencies |
| `make update` | Update Composer dependencies |
| `make test` | Run tests |
| `make phpstan` | Run static analysis (level max) |
| `make pint` | Fix code style (PSR-12) |
| `make rector` | Run automated refactoring |
| `make qa` | Run all checks |
| `make shell` | Open container shell |

## Disclaimer

This package is maintained by [Ipedis](https://www.ipedis.com). It is provided as-is under the terms of its license.
