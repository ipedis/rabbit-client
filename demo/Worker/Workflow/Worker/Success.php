<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker;


use AMQPEnvelope;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;


class Success extends ConnectorAbstract
{
    use WorkerTrait;

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

            return ["step" => "step1 finished"];
        };
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     */
    protected function getBindingKey()
    {
        return 'v1.admin.publication.success';
    }
}
