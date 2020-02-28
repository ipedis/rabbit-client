<?php

namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventDispatcher;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

class Dispatcher extends ConnectorAbstract
{
    use EventDispatcher;

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
        $this->connect();
    }

    public function main()
    {
        $this->dispatchEvent(EventMessagePayload::build(
            EventChannel::fromString('v1.admin.publication.was-exported'),
            [
                'publication' => [
                    'sid' => 3
                ]
            ]
        ));

        $this->dispatchEvent(EventMessagePayload::build(
            EventChannel::fromString('v1.admin.publication.was-deleted'),
            [
                'publication' => [
                    'sid' => 3
                ]
            ]
        ));
    }

    /**
     * @return ChannelFactory
     */
    public function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }
}
