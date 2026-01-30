<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow;

use Exception;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\Helper\Serializer;
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
    /**
     * Initialise collections
     */
    protected $groups = [];

    protected $workflowId;

    /**
     * Workflow constructor.
     *
     * @param Group|callable $firstStep
     */
    public function __construct($firstStep = null, array $groupCallbacks = [], protected ?WorkflowConfig $config = new WorkflowConfig(), ?string $workflowId = null)
    {
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
     * @throws InvalidWorkflowArgumentException
     * @throws InvalidGroupArgumentException
     */
    private function schedule($step, array $callbacks = []): void
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
    private function assertGroup($step): void
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
     * @throws Exception
     */
    public function findGroup(string $groupId): Group
    {
        $group = array_filter($this->getGroups(), fn (Group $group): bool => $group->getGroupId() === $groupId);

        if ($group === []) {
            throw new Exception('Group not found');
        }

        return reset($group);
    }

    public function find(string $orderId): Task
    {
        $task = null;
        foreach ($this->getGroups() as $group) {
            if ($group->has($orderId)) {
                $task = $group->find($orderId);
                break;
            }
        }

        if (is_null($task)) {
            throw new Exception('Task not found');
        }

        return $task;
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->groups as $group) {
            $errors = array_merge(
                $errors,
                array_map(fn (Task $task): \Ipedis\Rabbit\Exception\Helper\Error => Serializer::fromMessage($task->getLastReplyMessage()), $group->getFailedOrders())
            );
        }

        return $errors;
    }

    /**
     * Check if retry is allowed for task
     */
    public function canRetryTask(Task $task, Group $group): bool
    {
        return
            !$group->hasConfig() &&
            $this->getConfig()->hasToRetry() &&
            $task->getRetryCount() < $this->getConfig()->getMaxRetry();
    }

    public function getConfig(): WorkflowConfig
    {
        return $this->config;
    }

    public function setConfig(WorkflowConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * get current workflow progress percentage
     * @throws InvalidProgressValueException
     */
    public function getProgressPercentage(): float
    {
        return $this->getProgressBag()->getPercentage()->getCompleted();
    }

    public function getProgressBag(): WorkflowProgressBag
    {
        return new WorkflowProgressBag($this->getGroups(), $this->workflowId);
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::WORKFLOW_ALLOW_TYPES;
    }
}
