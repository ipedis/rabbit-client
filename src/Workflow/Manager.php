<?php

namespace Ipedis\Rabbit\Workflow;


use AMQPChannel;
use AMQPChannelException;
use AMQPConnectionException;
use AMQPQueue;
use AMQPQueueException;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadValidatorException;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
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
     * Store workflows dispatched
     *
     * @var array $workflowStore
     */
    private $workflowStore = [];

    /**
     * Store tasks dispatched
     *
     * @var array $taskStore
     */
    private $taskStore = [];

    abstract protected function getExchangeName(): string;

    protected function resetOrdersQueue()
    {
        $this->replyQueue = $this->createAnonymousQueue($this->channel);
    }

    public function run(Workflow $workflow)
    {
        $wasAtLeastOneFailure = false;

        /**
         * Add workflow to store
         */
        $this->addWorkflowToStore($workflow, null, null);

        /**
         * Channel factory must be provided to
         * construct/validate channel
         */
        $this->assertChannelFactory();

        /**
         * Message payload validator must be provided
         */
        $this->assertMessagePayloadValidator();

        /**
         * Each groups will be executed sequentially, we iterate on each group.
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

            /**
             * Dispatch all orders recursively for group
             */
            $this->dispatchGroupOrders($group, $workflow);

            $this->replyQueue->consume([$this, 'onTaskReply']);

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

    public function onTaskReply(\AMQPEnvelope $envelope, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $message = ReplyMessagePayload::fromJson($envelope->getBody());

        /**
         * Get tasks from store
         */
        $taskMetadata = $this->findTask($message->getOrderId());

        /**
         * Get workflow from store
         */
        $workflowMetadata   = $this->findWorkflow($taskMetadata['workflow']);

        /**
         * @var Workflow $workflow
         */
        $workflow               = $workflowMetadata['workflow'];
        $parentWorkflowId       = $workflowMetadata['parent'];
        $parentWorkflowGroupId  = $workflowMetadata['group'];

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
            $this->publish($task, $group, $workflow);

            $task->call(BindableEventInterface::TASK_ON_RETRY, $task);
            return true;
        }

        /**
         * Group has pending/running tasks
         * Wait for all tasks of the group to complete
         */
        if (!$group->getProgressBag()->isCompleted()) {
            return true;
        }

        /**
         * Sub Workflow has pending/running groups
         * Wait for all groups of the sub workflow to complete
         */
        if (
            !is_null($parentWorkflowId) &&
            !$workflow->getProgressBag()->isCompleted() &&
            $workflow->getProgressBag()->hasPendingGroups()
        ) {
            $nextGroup = $workflow->getProgressBag()->getNextPendingGroup();
            $this->dispatchGroupOrders($nextGroup, $workflow);

            return true;
        }

        /**
         * Call event binded on task layer.
         */
        $this->onUpdatedTaskStatus($message, $workflow, $group, $task);

        if (is_null($parentWorkflowId) && $group->getProgressBag()->isCompleted()) {
            return false;
        } else {
            $allParentsCompleted = $this->isParentWorkflowsCompleted($parentWorkflowId, $parentWorkflowGroupId);

            /**
             * wait until entire group is finish.
             */
            return !($group->getProgressBag()->isCompleted() && $allParentsCompleted);
        }
    }

    /**
     * @param $parentWorkflowId
     * @param $parentGroupId
     * @return bool
     * @throws \Exception
     */
    private function isParentWorkflowsCompleted($parentWorkflowId, $parentGroupId)
    {
        $workflowMetadata = $this->findWorkflow($parentWorkflowId);
        /**
         * @var Workflow $workflow
         */
        $workflow       = $workflowMetadata['workflow'];
        $workflowParent = $workflowMetadata['parent'];
        $workflowGroup  = $workflowMetadata['group'];

        if (is_null($workflowParent)) {
            // Root workflow
            $group = $workflow->findGroup($parentGroupId);
            if ($group->getProgressBag()->isCompleted()) {
                return true;
            }
        } else {
            // Sub Workflow
            if ($workflow->getProgressBag()->isCompleted()) {
                return $this->isParentWorkflowsCompleted($workflowParent, $workflowGroup);
            }
        }

        return false;
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
     * @param Group $group
     * @param Workflow $workflow
     * @return $this
     * @throws ChannelFactoryException
     * @throws MessagePayloadValidatorException
     */
    protected function publish(Task $task, Group $group, Workflow $workflow): self
    {
        /**
         * Add task metadata to store
         */
        $this->addTaskToStore($task, $group, $workflow);

        $message = $task->getOrderMessage();

        /**
         * Validate channel and return queue name
         */
        $channel = $this->getChannelName($message->getChannel());

        /**
         * Validate message payload data schema
         */
        $this->getMessagePayloadValidator()->validate($message);


        $message->setReplyQueue($this->replyQueue->getName());

        /**
         * Publish task on exchange
         */
        $this->exchange->publish(
            json_encode($message),
            $channel,
            AMQP_NOPARAM,
            $message->getMessageProperties()
        );

        return $this;
    }

    /**
     * Dispatch Group task recursively
     *
     * @param Group $group
     * @param Workflow $workflow
     */
    private function dispatchGroupOrders(Group $group, Workflow $workflow)
    {
        foreach ($group->getOrders() as $job) {
            if ($job instanceof Workflow) {
                $this->addWorkflowToStore($job, $workflow, $group);

                $nextGroup = $job->getProgressBag()->getNextPendingGroup();
                $this->dispatchGroupOrders($nextGroup, $job);
            } else {
                $this->publish($job, $group, $workflow);
                $job->setTaskAsDispatched();
                $job->call(BindableEventInterface::TASK_ON_START, $job);
            }
        }
    }

    /**
     * Create anonymous queue
     *
     * @param AMQPChannel $channel
     * @return AMQPQueue
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     */
    private function createAnonymousQueue(AMQPChannel $channel): AMQPQueue
    {
        $queue = new AMQPQueue($channel);
        $queue->setFlags(AMQP_EXCLUSIVE);
        $queue->declareQueue();

        return $queue;
    }

    /**
     * Store workflow metadata
     *
     * @param Workflow $workflow
     * @param Workflow|null $parentWorkflow
     * @param Group|null $parentGroup
     */
    private function addWorkflowToStore(Workflow $workflow, ?Workflow $parentWorkflow = null, ?Group $parentGroup = null)
    {
        $parentId       = !is_null($parentWorkflow) ? $parentWorkflow->getWorkflowId() : null;
        $parentGroupId  = !is_null($parentGroup) ? $parentGroup->getGroupId() : null;

        $this->workflowStore[$workflow->getWorkflowId()] = [
            'workflow' => $workflow,
            'parent'    => $parentId,
            'group'     => $parentGroupId
        ];
    }

    /**
     * @param string $workflowId
     * @return bool
     */
    private function hasWorkflow(string $workflowId): bool
    {
        return isset($this->workflowStore[$workflowId]);
    }

    /**
     * Find workflow by workflowId
     * @param string $workflowId
     * @return array
     */
    private function findWorkflow(string $workflowId): array
    {
        return $this->workflowStore[$workflowId];
    }

    /**
     * Store task metadata
     *
     * @param Task $task
     * @param Group $group
     * @param Workflow $workflow
     */
    private function addTaskToStore(Task $task, Group $group, Workflow $workflow)
    {
        $this->taskStore[$task->getTaskId()] = [
            'group'     => $group->getGroupId(),
            'workflow'  => $workflow->getWorkflowId()
        ];
    }

    /**
     * Find task by task id
     *
     * @param string $taskId
     * @return array
     */
    private function findTask(string $taskId): array
    {
        return $this->taskStore[$taskId];
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

    protected function assertChannelFactory(): void
    {
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }
    }

    /**
     * @throws MessagePayloadValidatorException
     */
    protected function assertMessagePayloadValidator(): void
    {
        if (!$this->getMessagePayloadValidator() instanceof ValidatorInterface) {
            throw new MessagePayloadValidatorException("Must provide message payload validator {messagePayloadValidator}");
        }
    }

    abstract protected function getChannelFactory();
}
