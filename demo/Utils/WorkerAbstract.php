<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Utils;

use Ipedis\Rabbit\Channel\Factory\ChannelFactory;

abstract class WorkerAbstract extends ConnectorAbstract
{
    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $exchange,
        string $type,
        private readonly ChannelFactory $channelFactory
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
    }

    protected function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }
}
