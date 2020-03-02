<?php

namespace Ipedis\Rabbit\Order;


use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\Exception\InvalidCallableException;
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
     * @var array $tasks
     */
    private $tasks = [];

    /**
     * Function to publish on queue new Message,
     * can have two optional parameters :
     * - $replyQueue
     * - $correlation_id will help worker to know where and what he have to answer when he have finish.
     *
     * @param OrderMessagePayload $messagePayload
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     */
    public function publishTask(OrderMessagePayload $messagePayload)
    {
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        /**
         * Validate channel and return queue name
         */
        $queue = $this->getQueueName($messagePayload->getChannel());

        /**
         * Push it to the pile of tasks for this queue.
         */
        $this->channel->basic_publish(
            (new AMQPMessage(json_encode($messagePayload), $messagePayload->getMessageProperties())),
            $this->getExchangeName(),
            $queue
        );

        /**
         * Add dispatched task to task list
         */
        $this->tasks[] = $messagePayload->getTaskId();
    }

    /**
     * Create anonymous and uniq queue.
     *
     * Generally use to have inLive queue callback to wait answer of our worker.
     *
     * @param string $indicator
     * @return string $callback_queue
     */
    public function createAnonymousQueue(string $indicator = "")
    {
        list($callback_queue, ,) = $this->channel->queue_declare($indicator,false,false,true);
        $this->channel->queue_bind($callback_queue, $this->getExchangeName());

        return $callback_queue;
    }

    /**
     * Callback to call when consuming message from queue
     *
     * @param $queue
     * @param $callBack
     * @throws InvalidCallableException
     */
    public function bindCallbackToQueue(string $queue, $callBack)
    {
        /**
         * If callback instance of MessageHandlerInterface,
         * automatically bind to method 'on'
         */
        if ($callBack instanceof MessageHandlerInterface) {
            $this->channel->basic_consume($queue,'',false,false,false,false,
                [$callBack, 'on']
            );

            return;
        }

        if (!is_callable($callBack)) {
            throw new InvalidCallableException(sprintf('Invalid callable provided for queue {%s}', $queue));
        }

        $this->channel->basic_consume($queue,'',false,false,false,false,
            $callBack //have to be array as [$this,"nameOfPublicMethod"]
        );
    }

    /**
     * Create anonymous queue and bind callback to it
     *
     * @param $callback
     * @param string $indicator
     * @return string
     * @throws InvalidCallableException
     */
    public function bindCallbackToAnonymousQueue($callback, string $indicator = '')
    {
        $queue = $this->createAnonymousQueue($indicator);
        $this->bindCallbackToQueue($queue, $callback);

        return $queue;
    }

    public function getTasks(): array
    {
        return $this->tasks;
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
