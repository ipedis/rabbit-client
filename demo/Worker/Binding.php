<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Event\EventListener;
use PhpAmqpLib\Message\AMQPMessage;

class Binding extends ConnectorAbstract
{
    use EventListener;

    /**
     * Process messages coming from queue
     *
     * @return Closure
     */
    protected function makeMessageHandler(): Closure
    {
        return function (AMQPMessage $msg) {
            var_dump(json_decode($msg->getBody(), true));
        };
    }

    /**
     * Handle errors during processing of message
     *
     * @return Closure
     */
    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, AMQPMessage $msg) {
            var_dump($exception->getMessage());
        };
    }

    protected function getBindingKey(): string
    {
        return 'v1.admin.publication.*';
    }
}
