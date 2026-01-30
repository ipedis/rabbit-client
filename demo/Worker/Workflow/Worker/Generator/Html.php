<?php

declare(strict_types=1);

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
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload): array {
            $this->printStatus($messagePayload, 'START');
            $params = $messagePayload->getData();
            sleep(random_int(1, 5));
            $this->printStatus($messagePayload, 'FINISH');
            return ["step" => "html finished"];
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, OrderMessagePayload $payload): void {
            printf("ERROR : %s", $exception->getMessage());
        };
    }

    /**
     * Can be string or array of keys
     */
    protected function getQueueName(): string
    {
        return 'v1.admin.publication.generate-html';
    }

    private function printStatus(OrderMessagePayload $message, string $status): void
    {
        file_put_contents('flow.log', sprintf("%s - %s \n", $message->getUuid(), $status), FILE_APPEND);
    }
}
