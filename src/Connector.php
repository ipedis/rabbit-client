<?php
/**
 * File: Connector.php
 * User: Yanis Ghidouche <yanis@ipedis.com>
 * Date: 01/11/2016 13:41
 */

namespace Ipedis\Rabbit;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

trait Connector
{
    /**
     * @var AMQPStreamConnection $connection
     */
    protected $connection = null;
    /**
     * @var AMQPChannel $channel
     */
    protected $channel = null;

    /**
     * @description connection to RabbitMQ
     */
    protected function connect()
    {
        $this->connection = new AMQPStreamConnection(
            $this->getHost(),
            $this->getPort(),
            $this->getUser(),
            $this->getPassword()

        );

        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(
            $this->getExchangeName(),
            $this->getExchangeType()
        );
    }

    /**
     * @description disconnect to RabbitMQ
     */
    protected function disconnect()
    {
        if($this->channel !== null) $this->channel->close();
        if($this->connection !== null) $this->connection->close();
    }

    abstract protected function getHost(): string;
    abstract protected function getPort(): int;
    abstract protected function getUser(): string;
    abstract protected function getPassword(): string;
    abstract protected function getExchangeName(): string;
    abstract protected function getExchangeType(): string;
}
