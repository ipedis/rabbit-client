<?php

namespace Ipedis\Demo\Rabbit\Worker;


use AMQPEnvelope;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;


class Step1 extends ConnectorAbstract
{
    use WorkerTrait;


    public static function getQueueName(): string
    {
        return OrderChannel::fromString('v1.admin.publication.step1');
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
            sleep(1);
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
            sleep(1);

            printf("On channel : %s Worker Name : %s (id : %s) \n",
                self::getQueueName(),
                self::class,
                $this->worker_id
            );

            return ["step" => "step1 finished"];
        };
    }
}
