<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;
use PhpAmqpLib\Message\AMQPMessage;

class Worker extends ConnectorAbstract
{
    use WorkerTrait;


    public static function getQueueName(): string
    {
        return OrderChannel::fromString('v1.admin.publication.generate');
    }

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPMessage $req, OrderMessagePayload $messagePayload) {
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
                $req,
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

            printf("On channel : %s Worker Name : %s (id : %s) as done task with id %s - Fail ? %s \n",
                self::getQueueName(),
                self::class,
                $this->worker_id,
                $messagePayload->getTaskId(),
                ($params["hasToFail"]) ? 'yes' : 'no'
            );

            if($params["hasToFail"]) throw new \Exception('oups something bad happen', 10);

            return ["foo" => "bar"];
        };
    }
}
