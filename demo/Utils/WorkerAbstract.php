<?php

namespace Ipedis\Demo\Rabbit\Utils;

use Ipedis\Rabbit\Channel\Factory\ChannelFactory;

abstract class WorkerAbstract extends ConnectorAbstract
{
    /**
     * @var ChannelFactory $channelFactory
     */
    private $channelFactory;

    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $exchange,
        string $type,
        ChannelFactory $channelFactory
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->channelFactory = $channelFactory;
    }

    /**
     * @return ChannelFactory
     */
    protected function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }

    /**
     * @return string
     */
    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }
}
