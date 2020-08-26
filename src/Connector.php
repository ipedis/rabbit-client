<?php

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
    protected $connection = null;

    /**
     * @var AMQPChannel $channel
     */
    protected $channel = null;

    /**
     * @var AMQPExchange $exchange
     */
    protected $exchange = null;
    /**
     * @var AMQPQueue[] Cached queue declaration indexed by queue name
     */
    protected $declaredQueues = [];

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
            throw new RabbitClientConnectException(sprintf('IPEDIS RABBIT CLIENT - Connection to rabbitMQ failed with error { %s }', $exception->getMessage()));
        }
    }

    /**
     * Disconnect to RabbitMQ
     */
    protected function disconnect()
    {
        if($this->channel !== null) $this->channel->close();
        if($this->connection !== null) $this->connection->disconnect();
    }

    /**
     * Helper function to publish message on exchange
     *
     * @param $message
     * @param $channel
     * @param array $messageProperties
     * @param bool $persistQueue
     * @throws RabbitClientPublishException
     */
    protected function publishToExchange($message, $channel, array $messageProperties = [], $persistQueue = false)
    {
        $routingKey = $this->getRoutingKeyWithPrefix($channel);
        try {
            if($persistQueue) $this->declareQueueBindingIfNecessary($routingKey);
            $this->exchange->publish($message, $routingKey, AMQP_NOPARAM, $messageProperties);
        } catch (\Exception $exception) {
            throw new RabbitClientPublishException(sprintf('IPEDIS RABBIT CLIENT - Publishing message on exchange failed with error { %s }', $exception->getMessage()));
        }
    }
    protected function declareQueueBindingIfNecessary(string $queueName)
    {
        if($this->exchange === null) $this->connect();
        if(!empty($this->declaredQueues[$queueName])) {
            $queue = new AMQPQueue(new AMQPChannel($this->connection));
            $queue->setFlags(AMQP_DURABLE);
            $queue->setName($queueName);
            $queue->declareQueue();
            $queue->bind($this->getExchangeName(), $queueName);
            // wW index queue declaration to easily find it back. also we keep reference of the object to be sure than queue are not destroyed
            $this->declaredQueues[$queueName] = $queue;
        }
    }

    /**
     * @param string $routingKey
     * @return string
     */
    protected function getRoutingKeyWithPrefix(string $routingKey)
    {
        if (empty($this->getQueuePrefix())) {
            return $routingKey;
        }

        return sprintf('%s.%s', $this->getQueuePrefix(), $routingKey);
    }

    /**
     * Create and declare exchange
     * AMQPC Exchange is the publishing mechanism
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    private function setExchange()
    {
        $this->exchange = new AMQPExchange($this->channel);
        $this->exchange->setType($this->getExchangeType());
        $this->exchange->setName($this->getExchangeName());
        $this->exchange->setFlags(AMQP_DURABLE);
        $this->exchange->declareExchange();
    }

    /**
     * Create and declare channel
     *
     * @throws \AMQPConnectionException
     */
    private function setChannel()
    {
        $this->channel = new AMQPChannel($this->connection);
        $this->channel->setPrefetchCount(1);
    }

    /**
     * Create AMQP Connection
     */
    private function getAMQPConnection(): AMQPConnection
    {
        $connection = new AMQPConnection([
            'host'      => $this->getHost(),
            'port'      => $this->getPort(),
            'login'     => $this->getUser(),
            'password'  => $this->getPassword()
        ]);

        $connection->connect();

        return $connection;
    }

    abstract public function getHost(): string;
    abstract public function getPort(): int;
    abstract public function getUser(): string;
    abstract public function getPassword(): string;
    abstract public function getExchangeName(): string;
    abstract public function getExchangeType(): string;

    /**
     * Optional prefix to attach to queue name.
     * In case system has multiple environments using same rabbitmq server.
     * @return string
     */
    public function getQueuePrefix(): string
    {
        return '';
    }
}
