<?php


namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Generator;

use AMQPEnvelope;
use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class Html extends WorkerAbstract
{
    use WorkerTrait;

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();
            var_dump('message received');

            sleep(rand(5, 10));

            return ["step" => "html finished"];
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, OrderMessagePayload $payload) {
            printf("ERROR : %s", $exception->getMessage());
        };
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     */
    protected function getQueueName()
    {
        return 'v1.admin.publication.generate-html';
    }
}
