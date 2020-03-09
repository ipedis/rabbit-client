<?php

namespace Ipedis\Rabbit;


use AMQPChannel;
use AMQPConnection;
use AMQPExchange;

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
            /**
             * @todo error handling
             */
           // var_dump(sprintf('Error:  %s', $exception->getMessage()));
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
     * Create and declare exchange
     * AMQPC Exchange is the publishing mechanism
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    private function setExchange()
    {
        $this->exchange = new AMQPExchange( $this->channel);
        $this->exchange->setType($this->getExchangeType());
        $this->exchange->setName($this->getExchangeName());
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
}
