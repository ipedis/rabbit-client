<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker;

use AMQPEnvelope;
use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class Failure extends WorkerAbstract implements OnBeforeMessage, OnAfterMessage
{
    use WorkerTrait;

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload): void {
            $params = $messagePayload->getData();
            $this->notifyTo(
                $message,
                ReplyMessagePayload::buildFromOrderMessagePayload(
                    $messagePayload,
                    MessageHandlerInterface::TYPE_PROGRESS,
                    ['status' => 'PROGRESS', 'step' => 1]
                )
            );

            throw new Exception('Lets fail :)');
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, OrderMessagePayload $payload): void {
            printf('In Exception Handler');
        };
    }

    /**
     * Can be string or array of keys
     */
    protected function getQueueName(): string
    {
        return 'v1.admin.publication.failure';
    }

    public function afterMessageHandled(): void
    {
        // Hook after message was handled
    }

    public function beforeMessageHandled(): void
    {
        // Hook before message was handled
    }
}
