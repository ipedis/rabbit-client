<?php

namespace Ipedis\Demo\Rabbit\Utils;

use Ipedis\Rabbit\Connector;


abstract class ConnectorAbstract
{
    use Connector;

    /**
     * @var string
     */
    private $host;
    /**
     * @var int
     */
    private $port;
    /**
     * @var string
     */
    private $user;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $exchange;
    /**
     * @var string
     */
    private $type;

    /**
     * Manager constructor.
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $exchange
     * @param string $type
     */
    public function __construct(string $host, int $port, string $user, string $password, string $exchange, string $type)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->exchange = $exchange;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    protected function getExchangeType(): string
    {
        return $this->type;
    }

    protected function getExchangeName(): string
    {
        return $this->exchange;
    }
}