<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
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
        return function (AMQPMessage $req) {
            //First step, you will get Parameters from Message Bag.
            $params = json_decode($req->body,true);

            /**
             * Do some traitment.
             *
             * [...]
             *
             * If everything is ok, reply to manager.
             */
            sleep(
                rand(
                    1,
                    3
                )
            );
            $this->notifyTo($req, ['status' => 'PROGRESS', 'step' => 1]);

            /**
             * Do some traitment.
             *
             * [...]
             *
             * If everything is ok, reply to manager.
             */
            sleep(
                rand(
                    1,
                    3
                )
            );

            printf("On channel : %s Worker Name : %s (id : %s) as done task with id %s - Fail ? %s \n",
                self::getQueueName(),
                self::class,
                $this->worker_id,
                (!$req->has('correlation_id'))?'unknown':$req->get('correlation_id'),
                ($params["hasToFail"]) ? 'yes' : 'no'
            );

            if($params["hasToFail"]) throw new \Exception('oups something bad happen', 10);

            return ["foo" => "bar"];
        };
    }
}
