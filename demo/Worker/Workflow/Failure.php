<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow;


use AMQPEnvelope;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;


class Failure extends ConnectorAbstract
{
    use WorkerTrait;


    public static function getQueueName(): string
    {
        return OrderChannel::fromString('v1.admin.publication.failure');
    }

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();

            throw new \Exception('Lets fail :)');


            return ["step" => "step2 finished"];
        };
    }
}
