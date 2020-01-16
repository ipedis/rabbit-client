<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Event\EventListener;
use PhpAmqpLib\Message\AMQPMessage;

class Binding extends ConnectorAbstract
{
    use EventListener;

    protected function getProcessing(): \Closure
    {
        return function (AMQPMessage $msg) {
            var_dump(json_decode($msg->getBody()));
        };
    }

    protected function getBindingKey(): string
    {
        return 'publication.*';
    }
}