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
use Ipedis\Rabbit\DTO\Store\TaskMeta;
use Ipedis\Rabbit\DTO\Store\WorkflowMeta;
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

    /**
     * Run workflow recursively
     *
     * @param Workflow $workflow
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     * @throws ChannelFactoryException
     * @throws MessagePayloadValidatorException
     * @throws \AMQPEnvelopeException
     */
    public function run(Workflow $workflow)
    {
        $wasAtLeastOneFailure = false;

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
         * Add workflow to store
         */
        $this->addWorkflowToStore($workflow, null, null);

        /**
         * HOOK WORKFLOW ON START
         * Call registered callbacks
         */
        $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        /**
         * Each groups will be executed sequentially, we iterate on each group.
         * @var Group $group
         */
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
             * Dispatch all orders of group recursively
             */
            $this->dispatchGroupOrders($group, $workflow);

            /**
             * Wait for all orders of current group to finish
             */
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

    /**
     * Executed when worker replies back
     *
     * @param \AMQPEnvelope $envelope
     * @param AMQPQueue $q
     * @return bool
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws ChannelFactoryException
     * @throws MessagePayloadValidatorException
     * @throws \Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException
     * @throws \Ipedis\Rabbit\Exception\Task\InvalidStatusException
     */
    public function onTaskReply(\AMQPEnvelope $envelope, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $message = ReplyMessagePayload::fromJson($envelope->getBody());

        /**
         * Get tasks & workflow from store
         */
        $taskMeta     = $this->findTask($message->getOrderId());
        $workflowMeta = $this->findWorkflow($taskMeta->getWorkflowId());
        $workflow     = $workflowMeta->getWorkflow();

        /**
         * @var Group $group
         * @var Task $task
         *
         * Persist current message AND update task status.
         * It will return current group and current task.
         */
        [$group, $task] = $workflow->taskReply($message);

        /**
         * ACK Current message.
         */
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
         * Call event binded on task layer.
         */
        $this->onUpdatedTaskStatus($message, $workflow, $group, $task);

        /**
         * Current group has pending tasks to be dispatched
         * (because of concurrency limit)
         */
        if ($group->getProgressBag()->hasPendingTasks()) {
            $this->dispatchGroupOrders($group, $workflow);

            return true;
        }

        /**
         * Group has pending/running tasks
         * Wait for all tasks of the current group to complete
         */
        if (!$group->getProgressBag()->isCompleted()) {
            return true;
        }

        /**
         * Sub Workflow has pending/running groups
         * Wait for all groups of the sub workflow to complete
         */
        if (
            !$workflowMeta->isRootWorkflow() &&
            !$workflow->getProgressBag()->isCompleted() &&
            $workflow->getProgressBag()->hasPendingGroups()
        ) {
            $nextGroup = $workflow->getProgressBag()->getNextPendingGroup();
            $this->dispatchGroupOrders($nextGroup, $workflow);

            return true;
        }

        if (
            $workflowMeta->isRootWorkflow()&&
            $group->getProgressBag()->isCompleted()
        ) {
            /**
             * Stop waiting as root workflow group has completed
             */
            return false;
        } else {
            /**
             * Check if all parent workflows are completed
             */
            $allParentsCompleted = $this->isParentWorkflowsCompleted(
                $workflowMeta->getParent(),
                $workflowMeta->getGroup()
            );

            /**
             * wait until entire group is finish.
             */
            return !($group->getProgressBag()->isCompleted() && $allParentsCompleted);
        }
    }

    /**
     * Create anonymous queue for workers to reply
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPQueueException
     */
    protected function resetOrdersQueue()
    {
        $this->replyQueue = $this->createAnonymousQueue($this->channel);
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
        $this->publishToExchange(
            json_encode($message),
            $channel,
            $message->getMessageProperties(),
            true
        );

        return $this;
    }

    /**
     * @throws ChannelFactoryException
     */
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

                $this->callWorkflowHookRecursively($workflow, BindableEventInterface::WORKFLOW_ON_TASKS_SUCCESS, $task);
                $this->callWorkflowHookRecursively($workflow, BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $task);
                break;
            case MessageHandlerInterface::TYPE_ERROR :
                $task->call(BindableEventInterface::TASK_ON_FAILURE, $task);
                $task->call(BindableEventInterface::TASK_ON_FINISH, $task);

                $group->call(BindableEventInterface::GROUP_ON_TASKS_FAILURE, $task);
                $group->call(BindableEventInterface::GROUP_ON_TASKS_FINISH, $task);

                $this->callWorkflowHookRecursively($workflow, BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, $task);
                $this->callWorkflowHookRecursively($workflow, BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $task);
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
     * @param $parentWorkflowId
     * @param $parentGroupId
     * @return bool
     * @throws \Exception
     */
    private function isParentWorkflowsCompleted($parentWorkflowId, $parentGroupId)
    {
        /**
         * Find workflow from store
         */
        $workflowMeta = $this->findWorkflow($parentWorkflowId);
        $workflow     = $workflowMeta->getWorkflow();

        if ($workflowMeta->isRootWorkflow()) {
            // Root workflow
            $group = $workflow->findGroup($parentGroupId);
            if ($group->getProgressBag()->isCompleted()) {
                return true;
            }
        } else {
            // Sub Workflow
            if ($workflow->getProgressBag()->isCompleted()) {
                return $this->isParentWorkflowsCompleted(
                    $workflowMeta->getParent(),
                    $workflowMeta->getGroup()
                );
            }
        }

        return false;
    }

    /**
     * Dispatch Group task recursively
     *
     * @param Group $group
     * @param Workflow $workflow
     * @throws ChannelFactoryException
     * @throws MessagePayloadValidatorException
     */
    private function dispatchGroupOrders(Group $group, Workflow $workflow)
    {
        foreach ($group->getOrders() as $job) {
            if ($job instanceof Workflow) {
                /**
                 * If workflow, dispatch next pending orders of workflow recursively
                 */
                $this->addWorkflowToStore($job, $workflow, $group);

                $nextGroup = $job->getProgressBag()->getNextPendingGroup();
                $this->dispatchGroupOrders($nextGroup, $job);
            } else {
                if ($workflow->getConfig()->hasConcurrencyLimitForChannel($job->getType())) {
                    /**
                     * Case when limited workers allocated per channel(task type)
                     * Publish orders until matches number of channel available for order
                     */
                    $limitForChannel = $workflow->getConfig()->getConcurrencyLimitForChannel($job->getType());
                    if (
                        $job->isPlanified() &&
                        $group->getProgressBag()->countDispatchedTasks($job->getType()) < $limitForChannel
                    ) {
                        $this->publish($job, $group, $workflow);
                        $job->setTaskAsDispatched();
                        $job->call(BindableEventInterface::TASK_ON_START, $job);
                    }
                } else {
                    $this->publish($job, $group, $workflow);
                    $job->setTaskAsDispatched();
                    $job->call(BindableEventInterface::TASK_ON_START, $job);
                }
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

        $this->workflowStore[$workflow->getWorkflowId()] = WorkflowMeta::build($workflow, $parentId, $parentGroupId);
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
     * @return WorkflowMeta
     */
    private function findWorkflow(string $workflowId): WorkflowMeta
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
        $this->taskStore[$task->getTaskId()] = TaskMeta::build(
            $group->getGroupId(),
            $workflow->getWorkflowId()
        );
    }

    /**
     * Find task by task id
     *
     * @param string $taskId
     * @return TaskMeta
     */
    private function findTask(string $taskId): TaskMeta
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

    /**
     * Call workflow Hook recursively
     *
     * @param Workflow $workflow
     * @param string $event
     * @param Task $task
     */
    private function callWorkflowHookRecursively(Workflow $workflow, string $event, Task $task)
    {
        /**
         * Call workflow hook
         */
        $workflow->call($event, $task);

        /**
         * Get current workflow meta from store
         */
        $workflowMeta = $this->findWorkflow($workflow->getWorkflowId());

        /**
         * Run parent workflow hook for event
         * unless disabled in config
         */
        if (
            !$workflow->getConfig()->ignoreParentHooks() &&
            !$workflowMeta->isRootWorkflow()
        ) {
            $workflowParentMeta = $this->findWorkflow($workflowMeta->getParent());
            $workflowParent     = $workflowParentMeta->getWorkflow();
            $this->callWorkflowHookRecursively($workflowParent, $event, $task);
        }
    }

    abstract protected function getChannelFactory();

    abstract protected function getExchangeName(): string;
}
