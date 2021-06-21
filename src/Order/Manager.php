<?php

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
use Ipedis\Rabbit\Exception\InvalidCallableException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadValidatorException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use function GuzzleHttp\default_user_agent;

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
     *
     * @var AMQPQueue $replyQueue
     */
    private AMQPQueue $replyQueue;

    /**
     * Collection of orders
     *
     * @var array $order
     */
    private array $orders;

    /**
     * Holds a collection of callable handlers to be
     * executed on defined events
     *
     * Only one handler is allowed for each event type
     *
     * @var array $handlers
     */
    private array $eventHandlers;

    public function resetOrdersQueue()
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
     * @param AMQPChannel $channel
     * @return AMQPQueue
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     */
    private function createAnonymousQueue(AMQPChannel $channel): AMQPQueue
    {
        $queue = new AMQPQueue($channel);
        $queue->setFlags(AMQP_EXCLUSIVE);
        $queue->declareQueue();

        return $queue;
    }

    /**
     * Publish an order
     *
     * @param OrderMessagePayload $messagePayload
     * @param $callback
     *
     * @return self
     *
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     * @throws InvalidCallableException
     * @throws MessagePayloadValidatorException
     */
    public function publish(OrderMessagePayload $messagePayload, $callback): self
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
     * @param OrderMessagePayload $messagePayload
     * @param $callback
     * @throws InvalidCallableException
     */
    protected function assertCallback(OrderMessagePayload $messagePayload, $callback): void
    {
        if (
            !is_callable($callback)
            && !$callback instanceof MessageHandlerInterface
        ) {
            throw new InvalidCallableException(sprintf('Invalid callable provided for chanel {%s}', $messagePayload->getChannel()));
        }
    }

    /**
     * @param $queueName
     * @return string
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
     *
     * @param string $orderId
     * @param callable $callback
     */
    private function addOrderToDispatchedList(string $orderId, $callback)
    {
        $this->orders[$orderId] = Order::build(
            $orderId,
            MessageHandlerInterface::TYPE_PROGRESS,
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
    public function run()
    {
        $this->replyQueue->consume([$this, 'onReply']);
    }

    /**
     * On reply callback from worker
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @return bool|void
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws MessagePayloadFormatException
     */
    public function onReply(AMQPEnvelope $message, AMQPQueue $q)
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
            return;
        }

        /**
         * Update order status on collection
         */
        $order = $this->updateOrderStatusInDispatchedList($order, $messagePayload->getStatus());

        /**
         * Get order callback
         */
        $callback = $order->getHandler();

        if ($order->getHandler() instanceof MessageHandlerInterface) {
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
    }

    /**
     * Get order from collection
     *
     * @param string $orderId
     * @return Order|null
     */
    private function getOrderFromDispatchedList(string $orderId): ?Order
    {
        if (isset($this->orders[$orderId])) {
            return $this->orders[$orderId];
        }

        return null;
    }

    /**
     * Update order status in collection
     *
     * @param Order $order
     * @param string $status
     * @return Order
     */
    private function updateOrderStatusInDispatchedList(Order $order, string $status): Order
    {
        $order->transitionTo($status);

        $this->orders[$order->getOrderId()] = $order;

        return $this->orders[$order->getOrderId()];
    }

    /**
     * Helper function to execute handlers for event
     *
     * @param string $status
     * @param MessagePayloadInterface $message
     */
    private function executeEventHandler(string $status, MessagePayloadInterface $message)
    {
        if (isset($this->eventHandlers[$status])) {
            $handler = $this->eventHandlers[$status];
            $handler($message);
        }
    }

    /**
     * Check if all orders have replied back
     *
     * @return bool
     */
    private function isCompleted(): bool
    {
        return count($this->getInProgressOrders()) === 0;
    }

    /**
     * Get collection of in progress orders
     *
     * @return array
     */
    public function getInProgressOrders(): array
    {
        return array_filter($this->orders, function (Order $order) {
            return $order->getStatus() === MessageHandlerInterface::TYPE_PROGRESS;
        });
    }

    /**
     * Bind handler to allowed event
     *
     * @param string $event
     * @param Closure $handler
     * @return self
     */
    public function bind(string $event, Closure $handler): self
    {
        if (in_array($event, MessageHandlerInterface::AVAILABLE_TYPES)) {
            $this->eventHandlers[$event] = $handler;
        }

        return $this;
    }

    /**
     * Get collection of successfully completed orders
     *
     * @return array
     */
    public function getSuccessfulOrders(): array
    {
        return array_filter($this->orders, function (Order $order) {
            return $order->getStatus() === MessageHandlerInterface::TYPE_SUCCESS;
        });
    }

    /**
     * Get collection of completed orders which have failed
     *
     * @return array
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->orders, function (Order $order) {
            return $order->getStatus() === MessageHandlerInterface::TYPE_ERROR;
        });
    }

    /**
     * Get percentage progression of orders
     *
     * @return float
     */
    public function getPercentageProgression(): float
    {
        $percentage = (count($this->getCompletedOrders()) / count($this->getDispatchedOrders())) * 100;

        return round($percentage, 2);
    }

    /**
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedOrders(): array
    {
        return array_filter($this->orders, function (Order $order) {
            return $order->getStatus() === MessageHandlerInterface::TYPE_SUCCESS ||
                $order->getStatus() === MessageHandlerInterface::TYPE_ERROR;
        });
    }

    /**
     * Get collection of dispatched orders
     *
     * @return array
     */
    public function getDispatchedOrders(): array
    {
        return $this->orders;
    }

    /**
     * @return string
     */
    abstract protected function getExchangeName(): string;
}
