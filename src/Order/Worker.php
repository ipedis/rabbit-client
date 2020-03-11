<?php

namespace Ipedis\Rabbit\Order;


use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use Closure;
use Exception;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

/**
 * Class Worker
 * @package Ipedis\Rabbit
 */
trait Worker
{
    /**
     * @var string $worker_id
     */
    protected $worker_id;

    /**
     * @var AMQPQueue $queue
     */
    protected $queue = null;

    /**
     * Method to initialise worker by
     * - connecting to rabbitMQ
     * - declare the queue
     * - declare callback to be used to consume message
     */
    public function execute()
    {
        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->queueDeclare();
        $this->queueConsume();

        $this->disconnect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Method first executed when receiving message
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @throws MessagePayloadFormatException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function main(AMQPEnvelope $message, AMQPQueue $q)
    {
        $this->ackEngine($message, $q, $this->makeMessageHandler());
    }

    /**
     * Notify manager with reply message and
     * acknowledge message as processed on rabbitMQ
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @param ReplyMessagePayload $replyToMessagePayload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function replyTo(AMQPEnvelope $message, AMQPQueue $q, ReplyMessagePayload $replyToMessagePayload)
    {
        /**
         * Notify manager with reply
         */
        $this->notifyTo($message, $replyToMessagePayload);

        /*
         * Acknowledging the message
         */
        $q->ack($message->getDeliveryTag());
    }

    /**
     * Notify manager with an update
     * Can be a progress update or the final reply back message
     *
     * @param AMQPEnvelope $message
     * @param ReplyMessagePayload $replyToMessagePayload
     * @return void
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function notifyTo(AMQPEnvelope $message, ReplyMessagePayload $replyToMessagePayload)
    {
        $defaultExchange = new AMQPExchange($this->channel);

        /**
         * Publishing to the same channel from the incoming message
         */
        $defaultExchange->publish(
            json_encode($replyToMessagePayload),
            $message->getReplyTo(),
            AMQP_NOPARAM,
            $replyToMessagePayload->getMessageProperties()
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
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @param Closure $onMessage
     * @throws MessagePayloadFormatException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    protected function ackEngine(AMQPEnvelope $message, AMQPQueue $q, Closure $onMessage)
    {
        /**
         * Re-construct message payload objectValue from request body
         */
        $messagePayload = OrderMessagePayload::fromJson($message->getBody());

        /**
         * let try to run the client callback. Otherwise catch the error.
         */
        try {
            $answer = $onMessage($message, $messagePayload);

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
                "correlation_id" => $messagePayload->getOrderId()
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
            $this->replyTo($message, $q, $replyToMessage);
        }
    }

    /**
     * Create new queue on rabbitMQ and
     * bind queue to exchange with routing key
     */
    protected function queueDeclare()
    {
        $this->queue = new AMQPQueue($this->channel);
        $this->queue->setName($this->getQueueName());
        $this->queue->setFlags(AMQP_DURABLE);
        $this->queue->declareQueue();

        $this->queue->bind($this->getExchangeName(), $this->getQueueName());
    }

    /**
     * Attach callback to queue
     *
     */
    protected function queueConsume()
    {
        $this->queue->consume([$this, "main"]); //$this->mainWork(AMQPMessage $req) callback
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
