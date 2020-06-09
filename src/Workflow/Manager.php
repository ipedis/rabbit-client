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
    private $workflow = [];

    /**
     * @var array $tasks
     */
    private $tasks;

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
        $this->workflow[$workflow->getWorkflowId()] = $workflow;
        /**
         * Each groups will be executed sequencially, we iterate on each group.
         * call relevant callback if needed.
         */
        $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

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
                /**
                 * Track current task
                 */
                $this->tasks[$task->getTaskId()] = [
                    'group'     => $group->getGroupId(),
                    'workflow'  => $workflow->getWorkflowId()
                ];


                $this->publish($task);
                $task->setTaskAsDispatched();
                $task->call(BindableEventInterface::TASK_ON_START, $task);
            }

            $this->replyQueue->consume([$this, 'onGroupReply']);

            $this->onGroupFinish($workflow, $group);

            if ($group->getProgressBag()->hasFailure()) {
                $wasAtLeastOneFailure = true;
                /**
                 * if workflow is configure to stop execution on first failure, Don't run next group.
                 */
                if(!$workflow->getConfig()->hasToContinueOnFailure()) break;
            }
        }
        /**
         * run is finish, let concluing workflow.
         */
        $workflow->call($wasAtLeastOneFailure ? BindableEventInterface::WORKFLOW_ON_FAILURE : BindableEventInterface::WORKFLOW_ON_SUCCESS);
        $workflow->call(BindableEventInterface::WORKFLOW_ON_FINISH);
    }

    public function onGroupReply(\AMQPEnvelope $envelope, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $message = ReplyMessagePayload::fromJson($envelope->getBody());

        /**
         * @var string
         */
        $taskArr = $this->tasks[$message->getOrderId()];

        /**
         * @var Workflow
         */
        $workflow = $this->workflow[$taskArr['workflow']];

        /**
         * @var Group $group
         * @var Task $task
         *
         * Persist current message AND update task status.
         * It will return current group and current task.
         */
        [$group, $task] = $workflow->taskReply($message);
        // ACK Current message.
        $q->ack($envelope->getDeliveryTag());


        /**
         * If task failed, check if retry is possible
         */
        if (
            $task->isOnFailure() &&
            ($group->canRetryTask($task) || $workflow->canRetryTask($task, $group))
        ) {
            $workflow->retryGroupTask($message);
            $this->publish($task);

            $task->call(BindableEventInterface::TASK_ON_RETRY, $task);
            return true;
        }

        /**
         * Call event binded on task layer.
         */
        $this->onUpdatedTaskStatus($message, $workflow, $group, $task);
        /**
         * wait until entire group is finish.
         */
        return (!$group->getProgressBag()->isCompleted());
    }

    /**
     * notify callback
     *
     * @param ReplyMessagePayload $message
     * @param Workflow $workflow
     * @param Group $group
     * @param Task $task
     */
    private function onUpdatedTaskStatus(
        ReplyMessagePayload $message,
        Workflow $workflow,
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

                $workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_SUCCESS, $task);
                $workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $task);
            break;
            case MessageHandlerInterface::TYPE_ERROR :
                $task->call(BindableEventInterface::TASK_ON_FAILURE, $task);
                $task->call(BindableEventInterface::TASK_ON_FINISH, $task);

                $group->call(BindableEventInterface::GROUP_ON_TASKS_FAILURE, $task);
                $group->call(BindableEventInterface::GROUP_ON_TASKS_FINISH, $task);

                $workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, $task);
                $workflow->call(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $task);
            break;
            case MessageHandlerInterface::TYPE_PROGRESS :
                $task->call(BindableEventInterface::TASK_ON_PROGRESS, $task);
            break;
        }
    }

    /**
     * @param Workflow $workflow
     * @param Group $group
     */
    private function onGroupFinish(Workflow $workflow, Group $group)
    {
        $group->call($group->getProgressBag()->hasFailure() ? BindableEventInterface::GROUP_ON_FAILURE : BindableEventInterface::GROUP_ON_SUCCESS, $group);
        $group->call(BindableEventInterface::GROUP_ON_FINISH, $group);
        $workflow->call($group->getProgressBag()->hasFailure() ? BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE : BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, $group);
        $workflow->call(BindableEventInterface::WORKFLOW_ON_GROUPS_FINISH, $group);
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
