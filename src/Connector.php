<?php

declare(strict_types=1);

namespace Ipedis\Rabbit;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;
use Ipedis\Rabbit\Exception\RabbitClientConnectException;
use Ipedis\Rabbit\Exception\RabbitClientPublishException;

trait Connector
{
    /**
     * @var AMQPConnection $connection
     */
    protected $connection;

    /**
     * @var AMQPChannel $channel
     */
    protected $channel;

    /**
     * @var AMQPExchange $exchange
     */
    protected $exchange;

    /**
     * @var AMQPQueue[] Cached queue declaration indexed by queue name
     */
    protected $declaredQueues = [];

    /**
     * Disconnect to RabbitMQ
     */
    protected function disconnect()
    {
        if (!empty($this->channel) && $this->channel instanceof AMQPChannel) {
            $this->channel->close();
        }

        if (!empty($this->connection) && $this->connection instanceof AMQPConnection) {
            $this->connection->disconnect();
        }
    }

    /**
     * Helper function to publish message on exchange
     *
     * @param $message
     * @param $channel
     * @param bool $persistQueue
     * @throws RabbitClientPublishException
     */
    protected function publishToExchange($message, $channel, array $messageProperties = [], $persistQueue = false)
    {
        $routingKey = $this->getRoutingKeyWithPrefix($channel);
        try {
            if ($persistQueue) {
                $this->declareQueueBindingIfNecessary($routingKey);
            }

            $this->exchange->publish($message, $routingKey, AMQP_NOPARAM, $messageProperties);
        } catch (\Exception $exception) {
            throw new RabbitClientPublishException(sprintf('IPEDIS RABBIT CLIENT - Publishing message on exchange failed with error { %s }', $exception->getMessage()), $exception->getCode(), $exception);
        }
    }

    protected function getRoutingKeyWithPrefix(string $routingKey): string
    {
        if (empty($this->getQueuePrefix())) {
            return $routingKey;
        }

        return sprintf('%s.%s', $this->getQueuePrefix(), $routingKey);
    }

    /**
     * Optional prefix to attach to queue name.
     * In case system has multiple environments using same rabbitmq server.
     */
    public function getQueuePrefix(): string
    {
        return '';
    }

    protected function declareQueueBindingIfNecessary(string $queueName)
    {
        if ($this->exchange === null) {
            $this->connect();
        }

        if (!empty($this->declaredQueues[$queueName])) {
            $amqpQueue = new AMQPQueue(new AMQPChannel($this->connection));
            $amqpQueue->setFlags(AMQP_DURABLE);
            $amqpQueue->setName($queueName);
            $amqpQueue->declareQueue();
            $amqpQueue->bind($this->getExchangeName(), $queueName);
            // wW index queue declaration to easily find it back. also we keep reference of the object to be sure than queue are not destroyed
            $this->declaredQueues[$queueName] = $amqpQueue;
        }
    }

    /**
     * Connection to rabbitMQ
     */
    protected function connect()
    {
        try {
            /**
             * Establish connection to AMQP
             */
            $this->connection = $this->getAMQPConnection();

            /**
             * Create and declare channel
             */
            $this->setChannel();

            /**
             * Create and declare exchange
             */
            $this->setExchange();
        } catch (\Exception $exception) {
            throw new RabbitClientConnectException(sprintf('IPEDIS RABBIT CLIENT - Connection to rabbitMQ failed with error { %s }', $exception->getMessage()), $exception->getCode(), $exception);
        }
    }

    /**
     * Create AMQP Connection
     */
    private function getAMQPConnection(): AMQPConnection
    {
        $amqpConnection = new AMQPConnection([
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'login' => $this->getUser(),
            'password' => $this->getPassword()
        ]);

        $amqpConnection->connect();

        return $amqpConnection;
    }

    abstract public function getHost(): string;

    abstract public function getPort(): int;

    abstract public function getUser(): string;

    abstract public function getPassword(): string;

    /**
     * Create and declare channel
     *
     * @throws \AMQPConnectionException
     */
    private function setChannel(): void
    {
        $this->channel = new AMQPChannel($this->connection);
        $this->channel->setPrefetchCount(1);
    }

    /**
     * Create and declare exchange
     * AMQPC Exchange is the publishing mechanism
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    private function setExchange(): void
    {
        $this->exchange = new AMQPExchange($this->channel);
        $this->exchange->setType($this->getExchangeType());
        $this->exchange->setName($this->getExchangeName());
        $this->exchange->setFlags(AMQP_DURABLE);
        $this->exchange->declareExchange();
    }

    abstract public function getExchangeType(): string;

    abstract public function getExchangeName(): string;
}
