<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Generator\Image;

use AMQPEnvelope;
use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class SimplePage extends WorkerAbstract
{
    use WorkerTrait;

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload): array {
            $params = $messagePayload->getData();

            sleep(random_int(1, 3));

            return ["step" => "simple img finished"];
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, OrderMessagePayload $payload): void {
        };
    }

    /**
     * Can be string or array of keys
     */
    protected function getQueueName(): string
    {
        return 'v1.admin.publication.generate-image-page';
    }
}
