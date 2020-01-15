<?php
/**
 * File: RabbitManager.php
 * User: Yanis Ghidouche <yanis@ipedis.com>
 * Date: 31/10/2016 15:17
 */

namespace Ipedis\Rabbit;
use PhpAmqpLib\Message\AMQPMessage;

trait Manager
{
    /**
     * @description create anonymous and uniq queue. Generally use to have inLive queue callback to wait answer of our worker.
     * @param string $indicator
     * @return string $callback_queue
     */
    public function createAnonymousQueue($indicator = "")
    {
        list($callback_queue, ,) = $this->channel->queue_declare($indicator,false,false,true);
        $this->channel->queue_bind($callback_queue, $this->getExchangeName());
        return $callback_queue;
    }

    public function bindCallbackToQueue($queue,array $callBack)
    {
        $this->channel->basic_consume($queue,'',false,false,false,false,
            $callBack //have to be array as [$this,"nameOfPublicMethod"]
        );
    }

    public function bindCallbackToAnonymousQueue(array $callback,$indicator = '')
    {
        $queue = $this->createAnonymousQueue($indicator);
        $this->bindCallbackToQueue($queue,$callback);
        return $queue;
    }

    /**
     * @description function to publish on queue new Message, can have two optional parameters.
     * $replyQueue and $correlation_id will help worker to know where and what he have to answer when he have finish.
     *
     * @param $queueName
     * @param $data
     * @param bool $replyQueue optional
     * @param bool $correlation_id optional
     */
    public function publishTask($queueName, $data, $replyQueue = false, $correlation_id = false)
    {
        $properties = [];
        if ($replyQueue && $correlation_id) {
            // case we will have eventCallback and manager will listen and wait the answser
            $properties = [
                'correlation_id' => $correlation_id,
                'reply_to' => $replyQueue
            ];
        }
        //craft associated message.
        $msg = new AMQPMessage(json_encode($data), $properties);
        //push it to the pile of tasks for this queue.
        $this->channel->basic_publish($msg,  $this->getExchangeName(), $queueName);
    }

    abstract protected function getExchangeName(): string;
}