<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Event\EventListener;
use PhpAmqpLib\Message\AMQPMessage;

class Binding extends ConnectorAbstract
{
    use EventListener;

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPMessage $msg) {
            var_dump(json_decode($msg->getBody(), true));
        };
    }

    protected function getBindingKey(): string
    {
        return 'v1.admin.publication.*';
    }
}
