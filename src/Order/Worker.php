<?php
/**
 * File: Worker.php
 * User: Yanis Ghidouche <yanis@ipedis.com>
 * Date: 01/11/2016 13:33
 */

namespace Ipedis\Rabbit\Order;

use Closure;
use Ipedis\Rabbit\Payload\OrderPayload;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class Worker
 * @package Ipedis\Rabbit
 */
trait Worker
{
    protected $worker_id;

    /**
     * @param AMQPMessage $req
     * @param array $options
     */
    public function replyTo(AMQPMessage $req, $options = [])
    {
        /**
         * Craft message.
         */
        $this->notifyTo($req, $options);
        /*
         * Acknowledging the message
         */
        $req->delivery_info['channel']->basic_ack(
            $req->delivery_info['delivery_tag'] //delivery tag
        );
    }

    /**
     * @param AMQPMessage $req
     * @param array $options
     * @return AMQPMessage
     */
    public function notifyTo(AMQPMessage $req, $options = []): AMQPMessage
    {
        /*
         * Creating a reply message with the same correlation id than the incoming message
         */
        $options['correlation_id'] = $req->get('correlation_id');

        $payload = new OrderPayload(
            $req->get('reply_to'),
            $req->get('correlation_id'),
            $options
        );

        $msg = new AMQPMessage(json_encode($payload), $options);

        /*
         * Publishing to the same channel from the incoming message
         */
        $req->delivery_info['channel']->basic_publish(
            (new AMQPMessage(json_encode($payload), $options)),                        //message
            '', //exchange
            $req->get('reply_to')       //routing key
        );

        return $msg;
    }


    /**
     * @description execution cycle time.
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
     *
     */
    protected function queueDeclare()
    {
        list($queue, ,) = $this->channel->queue_declare($this->getQueueName(), false, false, false, false);
        $this->channel->queue_bind($queue, $this->getExchangeName(), $this->getQueueName());
    }

    /**
     *
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
     * @param AMQPMessage $req
     */
    public function main(AMQPMessage $req)
    {
        $this->ackEngine($req, $this->makeMessageHandler());
    }


    protected function ackEngine(AMQPMessage $req, Closure $onMessage)
    {
        /**
         * let try to run the command. Otherwise catch the error.
         */
        try {
            $answser = array_merge(
                $onMessage($req), // Closure call
                [
                    'status' => 'SUCCESS'
                ]
            );
        } catch (\Exception $exception) {
            $answser = array_merge(
            [
                "queue" => $this->getQueueName(),
                "worker" => self::class,
                "id" => $this->worker_id,
                "correlation_id" => (!$req->has('correlation_id'))?'unknown':$req->get('correlation_id')
            ],
            [
                'status' => 'ERROR',
                'message' => $exception->getMessage(),
                'code' => $exception->getCode()
            ]);

        } finally {
            /**
             * We always consider current message as consumed
             * let the responsability of the manager to determine if
             * message have to be republished or not.
             */
            $this->replyTo($req, $answser);
        }
    }

    abstract public function getQueueName(): string;
    abstract protected function getExchangeName(): string;
    abstract protected function makeMessageHandler(): Closure;
}
