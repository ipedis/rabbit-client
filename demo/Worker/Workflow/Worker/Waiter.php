<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Worker;


use AMQPEnvelope;
use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class Waiter extends WorkerAbstract implements OnBeforeMessage, OnAfterMessage
{
    use WorkerTrait;

    protected function makeMessageHandler(): \Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();
            $this->notifyTo(
                $message,
                ReplyMessagePayload::buildFromOrderMessagePayload(
                    $messagePayload,
                    MessageHandlerInterface::TYPE_PROGRESS,
                    ['status' => 'PROGRESS', 'step' => 1]
                )
            );
            sleep(rand(0, 1));

            return ["step" => "step1 finished"];
        };
    }

    protected function makeExceptionHandler(): Closure
    {
        /**
         * @var $exception \Exception|MessagePayloadInvalidSchemaException
         */
        return function ($exception, OrderMessagePayload $payload) {
            printf('In Exception Handler');
        };
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     */
    protected function getQueueName()
    {
        return 'v1.admin.publication.waiter';
    }

    /**
     * Hook to call before worker handle message
     */
    public function beforeMessageHandled()
    {
        printf("WORKER LIFECYCLE HOOK : BEFORE HANDLING MESSAGE..."."\n\n");
    }

    public function afterMessageHandled()
    {
        printf("WORKER LIFECYCLE HOOK : AFTER HANDLING MESSAGE..."."\n\n");
    }
}
