<?php


namespace Ipedis\Rabbit\Workflow;


use AMQPChannel;
use AMQPChannelException;
use AMQPConnectionException;
use AMQPQueue;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;

trait Manager
{
    /**
     * The anonymous reply queue
     *
     * @var AMQPQueue $replyQueue
     */
    private $replyQueue;
    /**
     * @var Workflow
     */
    private $workflow;

    abstract protected function getExchangeName(): string;

    protected function resetOrdersQueue()
    {
        $this->replyQueue = $this->createAnonymousQueue($this->channel);
    }

    /**
     * Create anonymous queue
     *
     * @param AMQPChannel $channel
     * @return AMQPQueue
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     * @throws \AMQPQueueException
     */
    private function createAnonymousQueue(AMQPChannel $channel): AMQPQueue
    {
        $queue = new AMQPQueue($channel);
        $queue->setFlags(AMQP_EXCLUSIVE);
        $queue->declareQueue();

        return $queue;
    }


    public function run(Workflow $workflow)
    {
        $this->workflow = $workflow;
        /**
         * Each groups will be executed sequencially, we iterate on each group.
         * call relevant callback if needed.
         */
        $this->workflow->call(BindableEventInterface::WORKFLOW_START);

        /** @var Group $group */
        foreach ($workflow as $group) {

            /**
             * We start to run current group.
             */
            $group->call(BindableEventInterface::GROUP_START, $group);

            /**
             * we create a reply queue for each group runtime
             */
            $this->resetOrdersQueue();

            foreach ($group->getTasks() as $task) {
                $this->publish($task);
                $task->call(BindableEventInterface::TASK_START, $task);
            }

            $this->replyQueue->consume([$this, 'onGroupReply']);

            $group->call(BindableEventInterface::GROUP_FINISH, $group);

            if ($group->hasFailure()) {
                $this->workflow->call(BindableEventInterface::WORKFLOW_FAILURE, $group);

                break; // Don't run next group.
            }
        }
        /**
         * run is finish
         */
        $this->workflow->call(BindableEventInterface::WORKFLOW_FINISH);
    }

    public function onGroupReply(\AMQPEnvelope $envelope, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $message = ReplyMessagePayload::fromJson($envelope->getBody());

        $group = $this->workflow->taskReply($message);

        $q->ack($envelope->getDeliveryTag());

        /**
         * notify callback
         */
        if($message->getStatus() === MessageHandlerInterface::TYPE_SUCCESS)
            $group->call(BindableEventInterface::GROUP_SUCCESS, $group);

        if($message->getStatus() === MessageHandlerInterface::TYPE_ERROR)
            $group->call(BindableEventInterface::GROUP_FAILURE, $group);

        /**
         * wait until entire group is finish.
         */
        return (!$group->isFinish());
    }

    protected function publish(Task $task): self
    {
        $message = $task->getMessage();
        $message->setReplyQueue($this->replyQueue->getName());
        /**
         * Publish task on exchange
         */
        $this->exchange->publish(
            json_encode($message),
            $message->getChannel(),
            AMQP_NOPARAM,
            $message->getMessageProperties()
        );

        return $this;
    }
}
