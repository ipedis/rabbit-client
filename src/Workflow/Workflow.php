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
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class Workflow extends Bindable
{
    /**
     * @var list<Group>
     */
    protected array $groups = [];

    protected string $workflowId;

    /**
     * Workflow constructor.
     *
     * @param Group|callable(Group): (Group|void)|null $firstStep
     * @param array<string, callable|list<callable>> $groupCallbacks
     */
    public function __construct(Group|callable|null $firstStep = null, array $groupCallbacks = [], protected ?WorkflowConfig $config = new WorkflowConfig(), ?string $workflowId = null)
    {
        /**
         * $fistStep should be either a Group or a callable :
         * - Group : add group to collection
         * - Callable : create and provide new group to callable
         */
        if ($workflowId !== null) {
            $this->assertUuid($workflowId);
        }

        /** @var string $defaultUuid */
        $defaultUuid = uuid_create();
        $this->workflowId = $workflowId ?? $defaultUuid;
        if (!is_null($firstStep)) {
            $this->schedule($firstStep, $groupCallbacks);
        }
    }

    /**
     * @throws InvalidUuidException
     */
    protected function assertUuid(string $uuid): void
    {
        (new UuidValidator())->validate($uuid);
    }

    /**
     * Attach group of task to workflow
     * - Group provided, add group to collection
     * - Callable provided, create group and pass it to callable
     *   (which can add tasks to the group)
     *
     * @param Group|callable(Group): (Group|void) $step
     * @param array<string, callable|list<callable>> $callbacks
     * @throws InvalidGroupArgumentException
     */
    private function schedule(Group|callable $step, array $callbacks = []): void
    {
        if ($step instanceof Group) {
            /**
             * In case we provide groupCallback, we must bind it.
             */
            foreach ($callbacks as $eventType => $callback) {
                if (is_callable($callback)) {
                    $step->bind($eventType, $callback);
                } else {
                    foreach ($callback as $singleCallback) {
                        $step->bind($eventType, $singleCallback);
                    }
                }
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
     * Schedule next group of orders
     *
     * @param Group|callable(Group): (Group|void) $nextStep
     * @param array<string, callable|list<callable>> $callbacks
     * @throws InvalidGroupArgumentException
     */
    public function then(Group|callable $nextStep, array $callbacks = []): static
    {
        $this->schedule($nextStep, $callbacks);

        return $this;
    }

    /**
     * On task reply,
     * When we receive ReplyMessage from worker.
     *
     * @return array{0: Group, 1: Task}
     * @throws InvalidStatusException
     */
    public function taskReply(ReplyMessagePayload $message): array
    {
        // Check all existing group if he contain particular order id.
        foreach ($this->groups as $group) {
            if ($group->has($message->getOrderId())) {
                // Ask current group to update task based on received message.
                return $group->update($message);
            }
        }

        throw new Exception('No group found for order ' . $message->getOrderId());
    }

    /**
     * @return array{0: Group, 1: Task}
     * @throws InvalidStatusException
     */
    public function retryGroupTask(ReplyMessagePayload $message): array
    {
        // Check all existing group if he contain particular order id.
        foreach ($this->groups as $group) {
            if ($group->has($message->getOrderId())) {
                // Ask current group to update task based on received message.
                return $group->retryTask($message);
            }
        }

        throw new Exception('No group found for order ' . $message->getOrderId());
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
        foreach ($this->getGroups() as $group) {
            if ($group->has($orderId)) {
                return $group->find($orderId);
            }
        }

        throw new Exception('Task not found');
    }

    /**
     * @return list<Group>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return list<Error>
     */
    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->groups as $group) {
            $errors = array_merge(
                $errors,
                array_map(function (Task $task): Error {
                    $replyMessage = $task->getLastReplyMessage();
                    assert($replyMessage instanceof ReplyMessagePayload);

                    return Serializer::fromMessage($replyMessage);
                }, $group->getFailedOrders())
            );
        }

        return array_values($errors);
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
        assert($this->config instanceof WorkflowConfig);

        return $this->config;
    }

    public function setConfig(WorkflowConfig $config): static
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

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    /**
     * @return list<string>
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::WORKFLOW_ALLOW_TYPES;
    }
}
