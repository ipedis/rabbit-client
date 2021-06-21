<?php

namespace Ipedis\Rabbit\Workflow;

use Exception;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\Exception\Workflow\InvalidWorkflowArgumentException;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class Workflow extends Bindable
{
    /**
     * @var Group[] $groups
     */
    protected $groups;

    /**
     * @var WorkflowConfig
     */
    protected $config;

    protected $workflowId;

    /**
     * Workflow constructor.
     *
     * @param Group|callable $firstStep
     * @param array $groupCallbacks
     * @param WorkflowConfig|null $config
     * @param string|null $workflowId
     */
    public function __construct($firstStep = null, array $groupCallbacks = [], ?WorkflowConfig $config = null, ?string $workflowId = null)
    {
        /**
         * define config
         */
        $this->config = $config ?? new WorkflowConfig();

        /**
         * Initialise collections
         */
        $this->groups = [];

        /**
         * $fistStep should be either a Group or a callable :
         * - Group : add group to collection
         * - Callable : create and provide new group to callable
         */
        if ($workflowId) {
            $this->assertUuid($workflowId);
        }
        $this->workflowId = $workflowId ?? uuid_create();
        if (!is_null($firstStep)) {
            $this->schedule($firstStep, $groupCallbacks);
        }
    }

    /**
     * @param string $uuid
     * @throws InvalidUuidException
     */
    protected function assertUuid(string $uuid)
    {
        (new UuidValidator())->validate($uuid);
    }

    /**
     * Attach group of task to workflow
     * - Group provided, add group to collection
     * - Callable provided, create group and pass it to callable
     *   (which can add tasks to the group)
     *
     * @param $step
     * @param array $callbacks
     * @throws InvalidWorkflowArgumentException
     * @throws InvalidGroupArgumentException
     */
    private function schedule($step, array $callbacks = [])
    {
        $this->assertGroup($step);

        if ($step instanceof Group) {
            /**
             * In case we provide groupCallback, we must bind it.
             */
            foreach ($callbacks as $eventType => $callback) {
                $step->bind($eventType, $callback);
            }
            /**
             * Add group to collection
             */
            $this->groups[] = $step;
        } else {
            /**
             * Create and provide callable with new group
             */
            $workflowGroup = Group::build([], $callbacks);

            /**
             * Callable to add tasks in workflow group
             */
            $returnedGroup = $step($workflowGroup);

            /**
             * Plan/Schedule the workflow group
             * Add group to collection, if Group are returned by callback, then use it in place of the original one.
             */
            $this->groups[] = ($returnedGroup instanceof Group) ? $returnedGroup : $workflowGroup;
        }
    }

    /**
     * @param $step
     * @throws InvalidWorkflowArgumentException
     */
    private function assertGroup($step)
    {
        if (
            !$step instanceof Group &&
            !is_callable($step)
        ) {
            throw new InvalidWorkflowArgumentException(sprintf('Argument should be either instance of "%s" or a callable', Group::class));
        }
    }

    /**
     * Schedule next group of orders
     *
     * @param $nextStep
     * @param array $callbacks
     * @return Workflow
     * @throws InvalidWorkflowArgumentException
     * @throws InvalidGroupArgumentException
     */
    public function then($nextStep, array $callbacks = []): self
    {
        $this->schedule($nextStep, $callbacks);

        return $this;
    }

    /**
     * On task reply,
     * When we receive ReplyMessage from worker.
     *
     * @param ReplyMessagePayload $message
     * @return array
     * @throws InvalidStatusException
     */
    public function taskReply(ReplyMessagePayload $message): array
    {
        // Check all existing group if he contain particular order id.
        foreach ($this->groups as $group) {
            if ($group->has($message->getOrderId())) {
                // Ask current group to update task based on received message.
                $currentGroup = $group->update($message);
                break;
            }
        }

        return $currentGroup;
    }

    /**
     * @param ReplyMessagePayload $message
     * @return array
     * @throws InvalidStatusException
     */
    public function retryGroupTask(ReplyMessagePayload $message): array
    {
        // Check all existing group if he contain particular order id.
        foreach ($this->groups as $group) {
            if ($group->has($message->getOrderId())) {
                // Ask current group to update task based on received message.
                $currentGroup = $group->retryTask($message);
                break;
            }
        }

        return $currentGroup;
    }

    /**
     * Find group
     *
     * @param string $groupId
     * @return Group
     * @throws Exception
     */
    public function findGroup(string $groupId): Group
    {
        $group = array_filter($this->getGroups(), function (Group $group) use ($groupId) {
            return $group->getGroupId() === $groupId;
        });

        if (count($group) === 0) {
            throw new Exception('Group not found');
        }

        return reset($group);
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Check if retry is allowed for task
     *
     * @param Task $task
     * @param Group $group
     * @return bool
     */
    public function canRetryTask(Task $task, Group $group): bool
    {
        return
            !$group->hasConfig() &&
            $this->getConfig()->hasToRetry() &&
            $task->getRetryCount() < $this->getConfig()->getMaxRetry();
    }

    /**
     * @return WorkflowConfig
     */
    public function getConfig(): WorkflowConfig
    {
        return $this->config;
    }

    /**
     * @param WorkflowConfig $config
     * @return Workflow
     */
    public function setConfig(WorkflowConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * get current workflow progress percentage
     * @return float
     * @throws InvalidProgressValueException
     */
    public function getProgressPercentage(): float
    {
        return $this->getProgressBag()->getPercentage()->getCompleted();
    }

    /**
     * @return WorkflowProgressBag
     */
    public function getProgressBag(): WorkflowProgressBag
    {
        return new WorkflowProgressBag($this->getGroups(), $this->workflowId);
    }

    /**
     * @return string|null
     */
    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    /**
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::WORKFLOW_ALLOW_TYPES;
    }
}
