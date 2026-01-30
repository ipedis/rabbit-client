<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Event;

use AMQPEnvelope;
use AMQPQueue;
use Closure;
use Exception;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\Exception\RabbitClientConnectException;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

trait EventListener
{
    use Connector;

    protected string $worker_id;

    protected ?AMQPQueue $queue = null;

    /**
     * Instantiate event listener by
     * - Connect to rabbitMQ
     * - Create/declare queue and bind with exchange
     * - Define callback to be used for consuming message
     * @throws ChannelFactoryException|RabbitClientConnectException
     */
    public function execute(): void
    {
        /**
         * Before stating worker
         * Check if channel factory is defined
         * to validate event naming
         */
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->declareQueueIfNecessary();
        $this->queueConsume();

        $this->disconnect();
    }

    abstract protected function getChannelFactory();

    /**
     * Declare Queue and bind with exchange
     */
    protected function declareQueueIfNecessary()
    {
        $this->queue = new AMQPQueue($this->channel);
        $this->queue->setFlags(AMQP_EXCLUSIVE);
        $this->queue->declareQueue();
        $this->resolveRoutingKeys();
    }

    /**
     * Bind listener to multiple events
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    private function resolveRoutingKeys(): void
    {
        $routingKey = $this->getBindingKey();

        // If is string, we cast it to array.
        if (is_string($routingKey)) {
            $routingKey = [$routingKey];
        }

        if (is_array($routingKey)) {
            foreach ($routingKey as $key) {
                if (is_string($key)) {
                    $this->queue->bind($this->exchange->getName(), $this->getRoutingKeyWithPrefix($key));
                }
            }
        }
    }

    abstract protected function getBindingKey();

    /**
     * Define callback to be executed
     * when consuming message from queue
     */
    protected function queueConsume()
    {
        $this->queue->consume([$this, 'main']);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function main(AMQPEnvelope $message, AMQPQueue $q): void
    {
        try {
            /**
             * We have before message hook to run
             */
            if ($this instanceof OnBeforeMessage) {
                $this->beforeMessageHandled();
            }

            $this->consumeReceivedMessage($message);

            /**
             * We have after message hook to run
             */
            if ($this instanceof OnAfterMessage) {
                $this->afterMessageHandled();
            }
        } catch (Exception $exception) {
            /**
             * Handle exception from the hooks
             */
            $this->handleException($exception);
        }

        $q->ack($message->getDeliveryTag());
    }

    /**
     * Consume the received message
     *
     * Message will be handled ONLY if
     * listener is subscribed to the event
     *
     * @throws MessagePayloadFormatException
     */
    private function consumeReceivedMessage(AMQPEnvelope $envelope): void
    {
        /**
         * Ignore if message not proper json
         */
        if (!$this->isValidMessageFormat($envelope->getBody())) {
            return;
        }

        $message = EventMessagePayload::fromJson($envelope->getBody());

        try {
            /**
             * 1. Validate channel naming and return event name
             */
            if (!$this->isValidChannelName($message->getChannel())) {
                return;
            }

            /**
             * 2. Check if message is to be consumed
             */
            if ($this->isSubscribed($message->getChannel())) {
                /**
                 * 3. Process the message
                 */
                $this->handleReceivedMessage($message);
            }
        } catch (Exception $exception) {
            $this->handleException($exception, $message);
        }
    }

    /**
     * Check if message is valid json
     */
    private function isValidMessageFormat(string $message): bool
    {
        return json_decode($message) != null;
    }

    /**
     * Check if channel name follows proper naming
     */
    private function isValidChannelName(string $channelName): bool
    {
        return $this->getChannelFactory()->match($channelName);
    }

    /**
     * If you want to limit the call of callback for each message, you can filter by white list here.
     */
    protected function isSubscribed(string $eventName): bool
    {
        return true;
    }

    /**
     * Handle the message by calling dedicated callback handler
     * or the general callback handler
     */
    private function handleReceivedMessage(EventMessagePayload $message): void
    {
        $wasCalled = false;
        foreach ($this->getHandledMessages() as $channelName => $handledMessage) {
            if (
                !empty($handledMessage['method']) &&
                $message->getChannel() === $channelName
            ) {
                $this->callHandler($handledMessage, $message);
                $wasCalled = true;
            }
        }

        // If nobody was call, fallback to makeMessageHandler
        if (!$wasCalled) {
            $this->callHandler(['method' => 'makeMessageHandler'], $message);
        }
    }

    /**
     * By default there is no dedicated handler
     */
    protected function getHandledMessages(): iterable
    {
        return [];
    }

    /**
     * Helper to execute proper callback
     */
    private function callHandler(array $handler, EventMessagePayload $message): void
    {
        $result = $this->{$handler['method']}($message);
        if (is_callable($result)) {
            $result($message);
        }
    }

    /**
     * Handle exception by calling
     * client exception callback
     *
     * @param $exception
     */
    private function handleException($exception, ?EventMessagePayload $messagePayload = null): void
    {
        try {
            $this->makeExceptionHandler()($exception, $messagePayload);
        } catch (Exception $exception) {
            $this->logException($exception);
        }
    }

    abstract protected function makeExceptionHandler(): Closure;

    /**
     * Prototype method
     * Child can overide this function to log exceptions
     */
    protected function logException(Exception $exception)
    {
    }

    abstract protected function makeMessageHandler(): Closure;
}
