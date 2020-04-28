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
        $wasAtLeastOneFailure = false;
        $this->workflow = $workflow;
        /**
         * Each groups will be executed sequencially, we iterate on each group.
         * call relevant callback if needed.
         */
        $this->workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        /** @var Group $group */
        foreach ($workflow->getGroups() as $group) {

            /**
             * We start to run current group.
             */
            $group->call(BindableEventInterface::GROUP_ON_START, $group);

            /**
             * we create a reply queue for each group runtime
             */
            $this->resetOrdersQueue();

            foreach ($group->getTasks() as $task) {
                $this->publish($task);
                $task->setTaskAsDispatched();
                $task->call(BindableEventInterface::TASK_ON_START, $task);
            }

            $this->replyQueue->consume([$this, 'onGroupReply']);

            $this->onGroupFinish($group);

            if ($group->getProgressBag()->hasFailure()) {
                $wasAtLeastOneFailure = true;
                /**
                 * if workflow is configure to stop execution on first failure, Don't run next group.
                 */
                if(!$this->workflow->getConfig()->hasToContinueOnFailure()) break;
            }
        }
        /**
         * run is finish, let concluing workflow.
         */
        $this->workflow->call($wasAtLeastOneFailure ? BindableEventInterface::WORKFLOW_ON_FAILURE : BindableEventInterface::WORKFLOW_ON_SUCCESS);
        $this->workflow->call(BindableEventInterface::WORKFLOW_ON_FINISH);
    }

    public function onGroupReply(\AMQPEnvelope $envelope, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $message = ReplyMessagePayload::fromJson($envelope->getBody());

        /**
         * @var Group $group
         *
         * Persist current message AND update task status.
         * It will return current group and current task.
         */
        [$group, $task] = $this->workflow->taskReply($message);
        // ACK Current message.
        $q->ack($envelope->getDeliveryTag());
        /**
         * Call event binded on task layer.
         */
        $this->onUpdatedTaskStatus($message, $group, $task);

        /**
         * wait until entire group is finish.
         */
        return (!$group->getProgressBag()->isCompleted());
    }

    /**
     * notify callback
     *
     * @param ReplyMessagePayload $message
     * @param Group $group
     * @param Task $task
     */
    private function onUpdatedTaskStatus(
        ReplyMessagePayload $message,
        Group $group,
        Task $task
    ) {
        switch ($message->getStatus())
        {
            case MessageHandlerInterface::TYPE_SUCCESS :
                $task->call(BindableEventInterface::TASK_ON_SUCCESS, $task);
                $task->call(BindableEventInterface::TASK_ON_FINISH, $task);

                $group->call(BindableEventInterface::GROUP_ON_TASKS_SUCCESS, $task);
                $group->call(BindableEventInterface::GROUP_ON_TASKS_FINISH, $task);

                $this->workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_SUCCESS, $task);
                $this->workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $task);

            break;
            case MessageHandlerInterface::TYPE_ERROR :
                $task->call(BindableEventInterface::TASK_ON_FAILURE, $task);
                $task->call(BindableEventInterface::TASK_ON_FINISH, $task);
                $group->call(BindableEventInterface::GROUP_ON_TASKS_FAILURE, $task);
                $this->workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, $task);
            break;
            case MessageHandlerInterface::TYPE_PROGRESS :
                $task->call(BindableEventInterface::TASK_ON_PROGRESS, $task);
            break;
        }
    }

    /**
     * @param Group $group
     */
    private function onGroupFinish(Group $group)
    {
        $group->call($group->getProgressBag()->hasFailure() ? BindableEventInterface::GROUP_ON_FAILURE : BindableEventInterface::GROUP_ON_SUCCESS, $group);
        $group->call(BindableEventInterface::GROUP_ON_FINISH, $group);
        $this->workflow->call($group->getProgressBag()->hasFailure() ? BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE : BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, $group);
    }

    /**
     * @param Task $task
     * @return $this
     */
    protected function publish(Task $task): self
    {
        $message = $task->getOrderMessage();
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
