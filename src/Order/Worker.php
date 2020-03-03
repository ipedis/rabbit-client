<?php

namespace Ipedis\Rabbit\Order;

use Closure;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
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
     */
    public function main(AMQPMessage $req)
    {
        $this->ackEngine($req, $this->makeMessageHandler());
    }

    /**
     * @param AMQPMessage $req
     * @param ReplyToMessagePayload $replyToMessagePayload
     */
    public function replyTo(AMQPMessage $req, ReplyToMessagePayload $replyToMessagePayload)
    {
        /**
         * Craft message.
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
     * @param AMQPMessage $req
     * @param ReplyToMessagePayload $replyToMessagePayload
     * @return void
     */
    public function notifyTo(AMQPMessage $req, ReplyToMessagePayload $replyToMessagePayload)
    {
        /*
         * Publishing to the same channel from the incoming message
         */
        $req->delivery_info['channel']->basic_publish(
            (new AMQPMessage(json_encode($replyToMessagePayload), $replyToMessagePayload->getMessageProperties())),                        //message
            '', //exchange
            $req->get('reply_to') //routing key
        );
    }

    protected function ackEngine(AMQPMessage $req, Closure $onMessage)
    {
        /**
         * Create message payload objectValue from request body
         */
        $messagePayload = OrderMessagePayload::fromJson($req->getBody());

        /**
         * let try to run the command. Otherwise catch the error.
         */
        try {
            $answer = array_merge(
                $onMessage($req, $messagePayload), // Closure call
                [
                    'status' => 'SUCCESS'
                ]
            );
        } catch (\Exception $exception) {
            $answer = array_merge(
            [
                "queue" => $this->getQueueName(),
                "worker" => self::class,
                "id" => $this->worker_id,
                "correlation_id" => $messagePayload->getTaskId()
            ],
            [
                'status' => 'ERROR',
                'message' => $exception->getMessage(),
                'code' => $exception->getCode()
            ]);

        } finally {
            /**
             * Create new message to reply back to manager with
             * same taskId (correlation id)
             */
            $replyToMessage = ReplyToMessagePayload::buildFromOrderMessagePayload(
                $messagePayload,
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

    abstract public function getQueueName(): string;
    abstract protected function getExchangeName(): string;
    abstract protected function makeMessageHandler(): Closure;
}
