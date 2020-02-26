<?php

namespace Ipedis\Demo\Rabbit\Worker;


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
            var_dump($messagePayload->getChannel());
            var_dump($messagePayload->getData());
            var_dump($messagePayload->getHeaders());
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
        return 'v1.admin.publication.*';
    }
}
