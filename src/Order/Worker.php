<?php

namespace Ipedis\Rabbit\Order;


use Closure;
use Exception;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class Worker
 * @package Ipedis\Rabbit
 */
trait Worker
{
    protected $worker_id;

    /**
     * Method to initialise worker by
     * - connecting to rabbitMQ
     * - declare the queue
     * - wait for message
     */
    public function execute()
    {
        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->queueDeclare();
        $this->queueConsume();

        while(count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->disconnect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Method first executed when receiving message
     *
     * @param AMQPMessage $req
     * @throws MessagePayloadFormatException
     */
    public function main(AMQPMessage $req)
    {
        $this->ackEngine($req, $this->makeMessageHandler());
    }

    /**
     * Notify manager with reply message and
     * acknowledge message as processed on rabbitMQ
     *
     * @param AMQPMessage $req
     * @param ReplyMessagePayload $replyToMessagePayload
     */
    public function replyTo(AMQPMessage $req, ReplyMessagePayload $replyToMessagePayload)
    {
        /**
         * Notify manager with reply
         */
        $this->notifyTo($req, $replyToMessagePayload);

        /*
         * Acknowledging the message
         */
        $req->delivery_info['channel']->basic_ack(
            $req->delivery_info['delivery_tag'] //delivery tag
        );
    }

    /**
     * Notify manager with an update
     * Can be a progress update or the final reply back message
     *
     * @param AMQPMessage $req
     * @param ReplyMessagePayload $replyToMessagePayload
     * @return void
     */
    public function notifyTo(AMQPMessage $req, ReplyMessagePayload $replyToMessagePayload)
    {
        /*
         * Publishing to the same channel from the incoming message
         */
        $req->delivery_info['channel']->basic_publish(
            (new AMQPMessage(json_encode($replyToMessagePayload), $replyToMessagePayload->getMessageProperties())), //message
            '', //exchange
            $req->get('reply_to') //routing key
        );
    }

    /**
     * Helper method
     *
     * Consume message by calling client callback and
     * standardize the reply to manager
     *
     * - Notify status success if callback run successfully
     * - Notify status error if error captured while running client callback
     *
     * @param AMQPMessage $req
     * @param Closure $onMessage
     * @throws MessagePayloadFormatException
     */
    protected function ackEngine(AMQPMessage $req, Closure $onMessage)
    {
        /**
         * Re-construct message payload objectValue from request body
         */
        $messagePayload = OrderMessagePayload::fromJson($req->getBody());

        /**
         * let try to run the client callback. Otherwise catch the error.
         */
        try {
            $answer = $onMessage($req, $messagePayload);

            $status = MessageHandlerInterface::TYPE_SUCCESS;
            $answer = array_merge($answer, [
                'status' => $status
            ]);
        } catch (Exception $exception) {
            $status = MessageHandlerInterface::TYPE_ERROR;

            $answer = array_merge(
            [
                "queue"  => $this->getQueueName(),
                "worker" => self::class,
                "id"     => $this->worker_id,
                "correlation_id" => $messagePayload->getTaskId()
            ],
            [
                'status'  => $status,
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode()
            ]);
        } finally {
            /**
             * Create final message to reply back to manager with
             * same taskId (correlation id)
             */
            $replyToMessage = ReplyMessagePayload::buildFromOrderMessagePayload(
                $messagePayload,
                $status,
                $answer
            );

            /**
             * We always consider current message as consumed
             * let the responsibility of the manager to determine if
             * message have to be republished or not.
             */
            $this->replyTo($req, $replyToMessage);
        }
    }

    /**
     * Create new queue on rabbitMQ and
     * bind queue to exchange with routing key
     */
    protected function queueDeclare()
    {
        list($queue, ,) = $this->channel->queue_declare($this->getQueueName(), false, false, false, false);
        $this->channel->queue_bind($queue, $this->getExchangeName(), $this->getQueueName());
    }

    /**
     * Attach callback to queue
     */
    protected function queueConsume()
    {
        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume(
            $this->getQueueName(), //queue
            '', //consumer tag
            false, //no local
            false, //no ack
            false, //exclusive
            false, //no wait
            [$this,"main"] //$this->mainWork(AMQPMessage $req) callback
        );
    }

    /**
     * The queue name to be used by the worker
     *
     * @return string
     */
    abstract public function getQueueName(): string;

    /**
     * The exchange to be used to bind worker's queue
     *
     * @return string
     */
    abstract protected function getExchangeName(): string;

    /**
     * The client callback to be executed
     * on receiving a message
     *
     * @return Closure
     */
    abstract protected function makeMessageHandler(): Closure;
}
