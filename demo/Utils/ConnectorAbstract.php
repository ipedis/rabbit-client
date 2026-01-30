<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Utils;

use Ipedis\Rabbit\Connector;

abstract class ConnectorAbstract
{
    use Connector;

    /**
     * Manager constructor.
     */
    public function __construct(private string $host, private int $port, private string $user, private string $password, private string $exchangeName, private string $type)
    {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUser(): string
    {
        return $this->user;
    }

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
        return $this->exchangeName;
    }
}
