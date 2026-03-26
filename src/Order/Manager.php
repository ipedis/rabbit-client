<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Order;

use AMQPChannel;
use AMQPChannelException;
use AMQPConnectionException;
use AMQPEnvelope;
use AMQPEnvelopeException;
use AMQPQueue;
use AMQPQueueException;
use Closure;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\DTO\Order\Order;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\Exception\InvalidCallableException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadValidatorException;
use Ipedis\Rabbit\Exception\RabbitClientPublishException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

/**
 * Trait Manager
 *
 * @package Ipedis\Rabbit\Order
 * @method ChannelFactory|null matchPartial($channel)
 */
trait Manager
{
    /**
     * The anonymous reply queue
     */
    private AMQPQueue $replyQueue;

    /**
     * Collection of orders
     * @var array<string, Order>
     */
    private array $orders;

    /**
     * Holds a collection of callable handlers to be
     * executed on defined events
     *
     * Only one handler is allowed for each event type
     */
    private array $eventHandlers;

    public function resetOrdersQueue(): void
    {
        if ($this->channel === null) {
            $this->connect();
        }

        $this->orders = [];
        $this->eventHandlers = [];
        $this->replyQueue = $this->createAnonymousQueue($this->channel);
    }

    /**
     * Create anonymous queue
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     */
    private function createAnonymousQueue(AMQPChannel $channel): AMQPQueue
    {
        $amqpQueue = new AMQPQueue($channel);
        $amqpQueue->setFlags(AMQP_EXCLUSIVE);
        $amqpQueue->declareQueue();

        return $amqpQueue;
    }

    /**
     * Publish an order
     *
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     * @throws InvalidCallableException
     * @throws MessagePayloadValidatorException|MessagePayloadInvalidSchemaException|RabbitClientPublishException
     */
    public function publish(OrderMessagePayload $messagePayload, Closure|MessageHandlerInterface|null $callback = null): self
    {
        /**
         * Channel factory must be provided to
         * construct/validate channel
         */
        $this->assertChannelFactory();

        /**
         * Message payload validator must be provided
         */
        $this->assertMessagePayloadValidator();

        /**
         * Callback provided should be a callable OR
         * instance of MessageHandlerInterface
         *
         */
        if (is_null($callback)) {
            $callback = function (): void {
            };
        }

        $this->assertCallback($messagePayload, $callback);

        /**
         * Validate channel and return queue name
         */
        $channel = $this->getChannelName($messagePayload->getChannel());

        /**
         * Validate message payload data schema
         */
        $this->getMessagePayloadValidator()->validate($messagePayload);

        /**
         * Update headers of order message to add
         * - correlationId(Order Id)
         * - reply queue
         */
        $messagePayload->setReplyQueue($this->replyQueue->getName());

        /**
         * Add task to collection
         */
        $this->addOrderToDispatchedList(
            $messagePayload->getOrderId(),
            $callback
        );

        /**
         * Publish task on exchange
         */
        $this->publishToExchange(
            json_encode($messagePayload),
            $channel,
            $messagePayload->getMessageProperties(),
            true
        );

        return $this;
    }

    protected function assertChannelFactory(): void
    {
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }
    }

    abstract protected function getChannelFactory();

    /**
     * @throws MessagePayloadValidatorException
     */
    protected function assertMessagePayloadValidator(): void
    {
        if (!$this->getMessagePayloadValidator() instanceof ValidatorInterface) {
            throw new MessagePayloadValidatorException("Must provide message payload validator {messagePayloadValidator}");
        }
    }

    /**
     * @throws InvalidCallableException
     */
    protected function assertCallback(OrderMessagePayload $messagePayload, Closure|MessageHandlerInterface $callback): void
    {
        // Type system ensures callback validity via \Closure|MessageHandlerInterface
    }

    /**
     * @param $queueName
     * @throws ChannelNamingException
     */
    private function getChannelName($queueName): string
    {
        if (is_string($queueName)) {
            // if it is partial channel name
            if ($this->getChannelFactory()->matchPartial($queueName)) {
                return (string)$this->getChannelFactory()->getOrder($queueName);
            }

            // if it is full name, this will throw exception if full name is invalid.
            $eventObj = OrderChannel::fromString($queueName);

            return (string)$eventObj;
        }

        // if it is an instance, get channel full name
        if ($queueName instanceof OrderChannel) {
            return (string)$queueName;
        }

        // no criteria fulfilled, throw an exception.
        throw new ChannelNamingException('Invalid channel provided.');
    }

    /**
     * Append new order to collection
     */
    private function addOrderToDispatchedList(string $orderId, Closure|MessageHandlerInterface $callback): void
    {
        $this->orders[$orderId] = Order::build(
            $orderId,
            MessageHandlerInterface::TYPE_STARTING,
            $callback
        );
    }

    /**
     * Wait for all orders to reply back
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPEnvelopeException
     */
    public function run(): void
    {
        $this->replyQueue->consume([$this, 'onReply']);
    }

    /**
     * On reply callback from worker
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws MessagePayloadFormatException
     */
    public function onReply(AMQPEnvelope $message, AMQPQueue $q): ?bool
    {
        /**
         * Re-construct message payload from request body
         */
        $messagePayload = ReplyMessagePayload::fromJson($message->getBody());
        /**
         * Get order from collection
         */
        $order = $this->getOrderFromDispatchedList($messagePayload->getOrderId());
        if (is_null($order)) {
            return null;
        }

        /**
         * Update order status on collection
         */
        $order = $this->updateOrderStatusInDispatchedList($order, $messagePayload->getStatus());

        /**
         * Get order callback
         */
        $callback = $order->getHandler();

        if ($callback instanceof MessageHandlerInterface) {
            /**
             * Automatically bind to the on method
             */
            $callback->on($messagePayload);
        } else {
            $callback($messagePayload);
        }

        /**
         * Execute handler for current event if any
         */
        $this->executeEventHandler($messagePayload->getStatus(), $messagePayload);

        $q->ack($message->getDeliveryTag());

        /**
         * End callback if all task has finished
         */
        if ($this->isCompleted()) {
            return false;
        }

        return null;
    }

    /**
     * Get order from collection
     */
    private function getOrderFromDispatchedList(string $orderId): ?Order
    {
        return $this->orders[$orderId] ?? null;
    }

    /**
     * Update order status in collection
     */
    private function updateOrderStatusInDispatchedList(Order $order, string $status): Order
    {
        $order->transitionTo($status);

        $this->orders[$order->getOrderId()] = $order;

        return $this->orders[$order->getOrderId()];
    }

    /**
     * Helper function to execute handlers for event
     */
    private function executeEventHandler(string $status, MessagePayloadInterface $message): void
    {
        if (isset($this->eventHandlers[$status])) {
            $handler = $this->eventHandlers[$status];
            match ($status) {
                MessageHandlerInterface::TYPE_ERROR => $handler($message, Serializer::fromMessage($message)),
                default => $handler($message),
            };
        }
    }

    /**
     * Check if all orders have replied back
     */
    private function isCompleted(): bool
    {
        return count($this->getInProgressOrders()) === 0;
    }

    /**
     * Get collection of in progress orders
     */
    public function getInProgressOrders(): array
    {
        return array_filter($this->orders, fn (Order $order): bool => in_array($order->getStatus(), [MessageHandlerInterface::TYPE_PROGRESS, MessageHandlerInterface::TYPE_STARTING], true));
    }

    /**
     * Bind handler to allowed event
     */
    public function bind(string $event, Closure $handler): self
    {
        if (in_array($event, MessageHandlerInterface::AVAILABLE_TYPES, true)) {
            $this->eventHandlers[$event] = $handler;
        }

        return $this;
    }

    /**
     * Get collection of successfully completed orders
     */
    public function getSuccessfulOrders(): array
    {
        return array_filter($this->orders, fn (Order $order): bool => $order->getStatus() === MessageHandlerInterface::TYPE_SUCCESS);
    }

    /**
     * Get collection of completed orders which have failed
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->orders, fn (Order $order): bool => $order->getStatus() === MessageHandlerInterface::TYPE_ERROR);
    }

    /**
     * Get percentage progression of orders
     */
    public function getPercentageProgression(): float
    {
        $percentage = (count($this->getCompletedOrders()) / count($this->getDispatchedOrders())) * 100;

        return round($percentage, 2);
    }

    /**
     * Get collection of completed tasks
     */
    public function getCompletedOrders(): array
    {
        return array_filter($this->orders, fn (Order $order): bool => $order->getStatus() === MessageHandlerInterface::TYPE_SUCCESS ||
            $order->getStatus() === MessageHandlerInterface::TYPE_ERROR);
    }

    /**
     * Get collection of dispatched orders
     */
    public function getDispatchedOrders(): array
    {
        return $this->orders;
    }

    abstract protected function getExchangeName(): string;
}
