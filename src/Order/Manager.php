<?php
/**
 * File: RabbitManager.php
 * User: Yanis Ghidouche <yanis@ipedis.com>
 * Date: 31/10/2016 15:17
 */

namespace Ipedis\Rabbit\Order;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Trait Manager
 * @package Ipedis\Rabbit\Order
 * @method ChannelFactory|null matchPartial($channel)
 */
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
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     */
    public function publishTask($queueName, $data, $replyQueue = false, $correlation_id = false)
    {
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        $queue = $this->getQueueName($queueName);

        $properties = [];
        if ($replyQueue && $correlation_id) {
            // case we will have eventCallback and manager will listen and wait the answser
            $properties = [
                'correlation_id' => $correlation_id,
                'reply_to' => $replyQueue
            ];
        }

        //craft associated message.
        $payload = new OrderMessagePayload(
            $queue,
            (($correlation_id === false) ? $correlation_id : null),
            $data
        );

        //push it to the pile of tasks for this queue.
        $this->channel->basic_publish(
            (new AMQPMessage(json_encode($payload), $properties)),
            $this->getExchangeName(),
            $queue
        );
    }

    /**
     * @param $queueName
     * @return string
     * @throws ChannelNamingException
     */
    private function getQueueName($queueName): string
    {
        if (is_string($queueName)) {
            // if it is partial channel name
            if ($this->getChannelFactory()->matchPartial($queueName)) {
                return (string)$this->getChannelFactory()->getOrder($queueName);
            }

            // if it is full name, this will throw exception if full name is invalid.
            $eventObj = OrderChannel::fromString($queueName);

            return (string)$eventObj;
        }
        // if it is an instance, get channel full name
        if ($queueName instanceof OrderChannel) {
            return (string)$queueName;
        }

        // no criteria fulfilled, throw an exception.
        throw new ChannelNamingException('Invalid channel provided.');
    }

    abstract protected function getExchangeName(): string;
}
