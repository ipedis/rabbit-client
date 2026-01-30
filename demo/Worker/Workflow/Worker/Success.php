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

class Success extends WorkerAbstract implements OnBeforeMessage, OnAfterMessage
{
    use WorkerTrait;

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload): array {
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
            sleep(random_int(0, 1));

            return ["step" => "step1 finished"];
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
        return 'v1.admin.publication.success';
    }

    public function beforeMessageHandled(): void
    {
        // Hook before message was handled
    }

    public function afterMessageHandled(): void
    {
        // Hook after message was handled
    }
}
