<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker;


use AMQPEnvelope;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;


class Waiter extends ConnectorAbstract
{
    use WorkerTrait;

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();

            sleep(rand(0, 1));

            return ["step" => "step1 finished"];
        };
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     * @throws ChannelNamingException
     */
    protected function getBindingKey()
    {
        return OrderChannel::fromString('v1.admin.publication.waiter');
    }
}
