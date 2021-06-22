<?php

namespace Ipedis\Demo\Rabbit\Worker\Order;

use AMQPEnvelope;
use Closure;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Order\Worker as WorkerTrait;

class Worker extends WorkerAbstract implements OnBeforeMessage, OnAfterMessage
{
    use WorkerTrait;

    public const ENABLE_LIFE_CYCLE_PRINTING = true;

    protected function getQueueName()
    {
        return 'v1.admin.publication.generate';
    }

    protected function makeMessageHandler(): Closure
    {
        return function (AMQPEnvelope $message, OrderMessagePayload $messagePayload) {
            $params = $messagePayload->getData();
            $this->context->add('some', 'information');
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
                    ['status' => 'PROGRESS', 'step' => 1000]
                )
            );

            /**
             * Do some traitment.
             *
             * [...]
             *
             * If everything is ok, reply to manager.
             */

            printf(
                "Worker Name : %s (id : %s) as done task with id %s - Fail ? %s \n",
                self::class,
                $this->worker_id,
                $messagePayload->getOrderId(),
                ($params["hasToFail"]) ? 'yes' : 'no'
            );

            if ($params["hasToFail"]) {
                //throw new \Exception('oups something bad happen', 10);
            }

            return ["foo" => "bar"];
        };
    }

    /**
     * Handle errors during processing of message
     *
     * @return Closure
     */
    protected function makeExceptionHandler(): Closure
    {
        return function (\Exception $exception, ?OrderMessagePayload $messagePayload) {
            printf($exception->getMessage()."\n\n");
            // we can return extra context for better manager context handling.
            // you can also return a Context object if you prefer.
            return ['publication' => 1234, 'another' => 'context'];
        };
    }

    /**
     * Hook to call before worker handle message
     */
    public function beforeMessageHandled()
    {
        if (self::ENABLE_LIFE_CYCLE_PRINTING) {
            printf("Worker lifecycle hook : before handling message..."."\n\n");
        }
    }

    /**
     * Hook to call after worker handle message
     */
    public function afterMessageHandled()
    {
        if (self::ENABLE_LIFE_CYCLE_PRINTING) {
            printf("Worker lifecycle hook : after handling message..."."\n\n");
        }
    }

    public function getQueuePrefix(): string
    {
        return 'demo.order';
    }
}
