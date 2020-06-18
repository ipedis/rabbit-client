<?php


namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Generator\Image;

use AMQPEnvelope;
use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class ZoomableDoublePage extends ConnectorAbstract
{
    use WorkerTrait;

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();

            sleep(rand(1, 3));

            return ["step" => "dbl-zoomable img finished"];
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, OrderMessagePayload $payload) {

        };
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     */
    protected function getQueueName()
    {
        return 'v1.admin.publication.generate-image-dbl-zoomable';
    }

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }
}
