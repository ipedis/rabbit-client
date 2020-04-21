<?php

namespace Ipedis\Demo\Rabbit\Worker\Order;


use AMQPEnvelope;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;


class Worker extends ConnectorAbstract
{
    use WorkerTrait;

    protected function getQueueName()
    {
        return 'v1.admin.publication.generate';
    }

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();

            /**
             * Do some traitment.
             *
             * [...]
             *
             * If everything is ok, reply to manager.
             */
            $this->notifyTo(
                $message,
                ReplyMessagePayload::buildFromOrderMessagePayload(
                    $messagePayload,
                    MessageHandlerInterface::TYPE_PROGRESS,
                    ['status' => 'PROGRESS', 'step' => 1]
                )
            );

            /**
             * Do some traitment.
             *
             * [...]
             *
             * If everything is ok, reply to manager.
             */

            printf("Worker Name : %s (id : %s) as done task with id %s - Fail ? %s \n",
                self::class,
                $this->worker_id,
                $messagePayload->getOrderId(),
                ($params["hasToFail"]) ? 'yes' : 'no'
            );

            if($params["hasToFail"]) throw new \Exception('oups something bad happen', 10);

            return ["foo" => "bar"];
        };
    }
}
