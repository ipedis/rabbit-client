<?php

namespace Ipedis\Rabbit\Order;


use AMQPQueue;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\DTO\Task\Task;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\Exception\InvalidCallableException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;

/**
 * Trait Manager
 * @package Ipedis\Rabbit\Order
 * @method ChannelFactory|null matchPartial($channel)
 */
trait Manager
{
    /**
     * @var array $dispatchedTasks
     */
    private $dispatchedTasks = [];

    /**
     * Method to publish new message/task on queue
     *
     * @param OrderMessagePayload $messagePayload
     * @return string TaskId
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     */
    public function publishTask(OrderMessagePayload $messagePayload): string
    {
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        /**
         * Validate channel and return queue name
         */
        $channel = $this->getChannelName($messagePayload->getChannel());

        /**
         * Push it to the pile of tasks for this queue.
         */
        $this->exchange->publish(
            json_encode($messagePayload),
            $channel,
            AMQP_NOPARAM,
            $messagePayload->getMessageProperties()
        );

        /**
         * Add task to dispatched task list
         */
        $this->addToDispatchedTasks($messagePayload);

        return $messagePayload->getTaskId();
    }

    /**
     * Create anonymous and uniq queue.
     *
     * Generally use to have inLive queue callback to wait answer of our worker.
     *
     * @param string $indicator
     * @return AMQPQueue $callback_queue
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPQueueException
     */
    public function createAnonymousQueue(string $indicator = ""): AMQPQueue
    {
        $callbackQueue = new AMQPQueue($this->channel);
        $callbackQueue->setFlags(AMQP_EXCLUSIVE);

        if (!empty($indicator)) {
            $callbackQueue->setName($indicator);
        }

        $callbackQueue->declareQueue();

        return $callbackQueue;
    }

    public function waitForReplies(AMQPQueue $anoQueue, $callback)
    {
        /**
         * If callback instance of MessageHandlerInterface,
         * automatically bind to method 'on'
         */
        if ($callback instanceof MessageHandlerInterface) {
            $anoQueue->consume([$callback, 'on']);

            return;
        }

        if (!is_callable($callback)) {
            throw new InvalidCallableException(sprintf('Invalid callable provided for queue {%s}', $anoQueue));
        }

        $anoQueue->consume($callback);// callback have to be array as [$this,"nameOfPublicMethod"]
    }

    /**
     * Get collection of dispatched task
     *
     * @return array
     */
    public function getDispatchedTasks(): array
    {
        return $this->dispatchedTasks;
    }

    /**
     * Helper to get progression rate
     *
     * @param array $completedTasks
     * @return float
     */
    public function getPercentageCompletedTasks(array $completedTasks): float
    {
        return round((count($completedTasks) / count($this->dispatchedTasks)) * 100);
    }

    /**
     * @param $queueName
     * @return string
     * @throws ChannelNamingException
     */
    private function getChannelName($queueName): string
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

    /**
     * Add task to dispatched task list
     *
     * @param OrderMessagePayload $messagePayload
     */
    private function addToDispatchedTasks(OrderMessagePayload $messagePayload)
    {
        $this->dispatchedTasks[] = Task::build(
            $messagePayload->getTaskId(),
            MessageHandlerInterface::TYPE_PROGRESS
        );
    }

    abstract protected function getExchangeName(): string;
}
