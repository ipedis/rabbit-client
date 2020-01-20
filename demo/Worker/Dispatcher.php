<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Event\EventDispatcher;

class Dispatcher extends ConnectorAbstract
{
    use EventDispatcher;

    public function __construct(string $host, int $port, string $user, string $password, string $exchange, string $type)
    {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->connect();
    }

    public function main()
    {
        $this->dispatchEvent(EventChannel::fromString('v1.admin.publication.was-exported'), [
            'publication' => [
                'sid' => 3
            ]
        ]);

        $this->dispatchEvent(EventChannel::fromString('v1.admin.publication.was-deleted'), [
            'publication' => [
                'sid' => 3
            ]
        ]);
    }
}
