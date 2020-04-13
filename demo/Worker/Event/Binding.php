<?php

namespace Ipedis\Demo\Rabbit\Worker\Event;


use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Event\EventListener;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

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
        return function (EventMessagePayload $messagePayload) {
            print_r($messagePayload->getData(), $messagePayload->getChannel());
        };
    }

    /**
     * Handle errors during processing of message
     *
     * @return Closure
     */
    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, EventMessagePayload $messagePayload) {
            var_dump($exception->getMessage());
        };
    }

    protected function getBindingKey(): string
    {
        return '#';
    }
}
