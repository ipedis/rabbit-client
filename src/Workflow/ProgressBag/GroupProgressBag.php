<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\Workflow\ProgressBag\Contract\ProgressBagInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\TaskProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\TaskProgress;

class GroupProgressBag implements ProgressBagInterface
{
    /**
     * GroupProgressBag constructor.
     *
     * @param array<string, Task|Workflow> $orders Group orders
     */
    public function __construct(
        private readonly array $orders,
        private readonly string $groupId
    ) {
    }

    public function hasPendingTasks(): bool
    {
        return $this->countPlanifiedTasks() > 0;
    }

    /**
     * Count of planified tasks
     */
    public function countPlanifiedTasks(): int
    {
        return count($this->getPlanifiedTasks());
    }

    /**
     * Get collection of planified tasks
     * waiting to be dispatched
     *
     * @return list<Task>
     */
    public function getPlanifiedTasks(): array
    {
        return array_values(array_filter($this->getTasksInGroup(), fn (Task $task): bool => $task->isPlanified()));
    }

    /**
     * Get Tasks inside group recursively
     *
     * @param array<string, Task|Workflow>|null $orders
     * @return list<Task>
     */
    public function getTasksInGroup(?array $orders = null): array
    {
        $orders ??= $this->orders;

        $groupTasks = [];
        foreach ($orders as $order) {
            if ($order instanceof Workflow) {
                foreach ($order->getGroups() as $group) {
                    $groupTasks = array_merge($groupTasks, $this->getTasksInGroup($group->getOrders()));
                }
            } else {
                $groupTasks[] = $order;
            }
        }

        return $groupTasks;
    }

    /**
     * Count of in progress tasks
     */
    public function countInProgressTasks(): int
    {
        return count($this->getInProgressTasks());
    }

    /**
     * Get collection of in progress tasks
     *
     * @return list<Task>
     */
    public function getInProgressTasks(): array
    {
        return array_values(array_filter($this->getTasksInGroup(), fn (Task $task): bool => $task->isInProgress()));
    }

    /**
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     * @throws InvalidUuidException
     * @throws InvalidProgressValueException
     */
    public function getGroupProgress(): GroupProgress
    {
        return GroupProgress::build(
            $this->getGroupId(),
            $this->getStatus(),
            $this->getTimer(),
            $this->getPercentage(),
            new TaskProgressCollection(array_map(fn (Task $task): TaskProgress => $task->getTaskProgress(), $this->getTasksInGroup()))
        );
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * Status of group.
     * Can be in:
     * - PENDING : no tasks yet dispatched
     * - RUNNING : at least one task has been dispatched
     * - FINISHED : all tasks in group have completed
     */
    public function getStatus(): Status
    {
        if ($this->isCompleted()) {
            if ($this->hasFailure()) {
                return Status::buildFailed();
            }

            return Status::buildSuccess();
        }

        if ($this->isPending()) {
            return Status::buildPending();
        }

        return Status::buildRunning();
    }

    /**
     * Has all tasks in group completed
     */
    public function isCompleted(): bool
    {
        return $this->countTasksInGroup() === $this->countCompletedTasks();
    }

    /**
     * Count of orders in group
     */
    public function countTasksInGroup(): int
    {
        return count($this->getTasksInGroup());
    }

    /**
     * Count of completed tasks
     */
    public function countCompletedTasks(): int
    {
        return count($this->getCompletedTasks());
    }

    /**
     * Get collection of completed tasks
     *
     * @return list<Task>
     */
    public function getCompletedTasks(): array
    {
        return array_values(array_filter($this->getTasksInGroup(), fn (Task $task): bool => $task->isCompleted()));
    }

    /**
     * At least a task has failed
     */
    public function hasFailure(): bool
    {
        return $this->countFailedTasks() > 0;
    }

    /**
     * Count of failed tasks
     */
    public function countFailedTasks(): int
    {
        return count($this->getFailedTasks());
    }

    /**
     * Get collection of completed tasks which have failed
     *
     * @return list<Task>
     */
    public function getFailedTasks(): array
    {
        return array_values(array_filter($this->getTasksInGroup(), fn (Task $task): bool => $task->isOnFailure()));
    }

    /**
     * No task yet dispatched
     */
    public function isPending(): bool
    {
        return !$this->isCompleted() && $this->countDispatchedTasks() === 0;
    }

    /**
     * Count of dispatched tasks
     */
    public function countDispatchedTasks(?string $taskType = null): int
    {
        return count($this->getDispatchedTasks($taskType));
    }

    /**
     * Get Collection of dispatched tasks
     *
     * @return list<Task>
     */
    public function getDispatchedTasks(?string $taskType = null): array
    {
        return array_values(array_filter($this->getTasksInGroup(), function (Task $task) use ($taskType): bool {
            if (!is_null($taskType)) {
                return $task->isDispatched() && $task->getType() === $taskType;
            }

            return $task->isDispatched();
        }));
    }

    /**
     * At least a task has been dispatched
     */
    public function isRunning(): bool
    {
        return !$this->isPending();
    }

    /**
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public function getTimer(): Timer
    {
        return Timer::build(
            $this->getExecutionTime(),
            $this->getStartedAt(),
            $this->getFinishedAt()
        );
    }

    /**
     * Get Group execution time
     */
    public function getExecutionTime(): float
    {
        $totalExecutionTime = 0;

        if ($this->isPending()) {
            return $totalExecutionTime;
        }

        foreach ($this->getCompletedTasks() as $task) {
            $totalExecutionTime += $task->getExecutionTime();
        }

        return $totalExecutionTime;
    }

    /**
     * Iterate through tasks and find
     * first started task
     */
    public function getStartedAt(): ?\DateTime
    {
        $startTime = null;

        /**
         * No tasks of group yet dispatched
         */
        if ($this->isPending()) {
            return $startTime;
        }

        foreach ($this->getTasksInGroup() as $task) {
            /**
             * Ignore task not yet started
             */
            if ($task->isPlanified()) {
                continue;
            }

            if ($task->isDispatched()) {
                continue;
            }

            if (is_null($task->getStartTime())) {
                continue;
            }

            if (is_null($startTime)) {
                $startTime = $task->getStartTime();
            } elseif ($task->getStartTime() < $startTime) {
                $startTime = $task->getStartTime();
            }
        }

        return $startTime;
    }

    /**
     * Iterate through tasks and find
     * last completed task
     */
    public function getFinishedAt(): ?\DateTime
    {
        $finishTime = null;

        /**
         * Tasks are still pending in group
         */
        if (!$this->isCompleted()) {
            return $finishTime;
        }

        foreach ($this->getTasksInGroup() as $task) {
            /**
             * If for any reason task does not have finish time
             */
            if (is_null($task->getFinishedTime())) {
                continue;
            }

            if (is_null($finishTime)) {
                $finishTime = $task->getFinishedTime();
            } elseif ($task->getFinishedTime() > $finishTime) {
                $finishTime = $task->getFinishedTime();
            }
        }

        return $finishTime;
    }

    /**
     * Get percentage progression of group
     *
     * @throws InvalidProgressValueException
     */
    public function getPercentage(): Percentage
    {
        $totalTasks = $this->countTasksInGroup();

        return Percentage::build(
            Percentage::calculate($this->countCompletedTasks(), $totalTasks),
            Percentage::calculate($this->countSuccessfulTasks(), $totalTasks),
            Percentage::calculate($this->countFailedTasks(), $totalTasks)
        );
    }

    /**
     * Count of successful tasks
     */
    public function countSuccessfulTasks(): int
    {
        return count($this->getSuccessfulTasks());
    }

    /**
     * Get collection of successfully completed tasks
     *
     * @return list<Task>
     */
    public function getSuccessfulTasks(): array
    {
        return array_values(array_filter($this->getTasksInGroup(), fn (Task $task): bool => $task->isSuccess()));
    }
}
