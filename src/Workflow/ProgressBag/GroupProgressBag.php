<?php

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

class GroupProgressBag implements ProgressBagInterface
{
    /**
     * Group orders
     *
     * @var array
     */
    private array $orders = [];
    /**
     * @var string
     */
    private string $groupId;

    /**
     * GroupProgressBag constructor.
     * @param array $orders
     * @param string $groupId
     */
    public function __construct(array $orders, string $groupId)
    {
        $this->orders = $orders;
        $this->groupId = $groupId;
    }

    /**
     * @return bool
     */
    public function hasPendingTasks(): bool
    {
        return $this->countPlanifiedTasks() > 0;
    }

    /**
     * Count of planified tasks
     *
     * @return int
     */
    public function countPlanifiedTasks(): int
    {
        return count($this->getPlanifiedTasks());
    }

    /**
     * Get collection of planified tasks
     * waiting to be dispatched
     *
     * @return array
     */
    public function getPlanifiedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) {
            return $task->isPlanified();
        });
    }

    /**
     * Get Tasks inside group recursively
     *
     * @param array|null $orders
     * @return array
     */
    public function getTasksInGroup(?array $orders = null): array
    {
        $orders = $orders ?? $this->orders;

        return array_reduce($orders, function ($groupTasks, $order) {
            if ($order instanceof Workflow) {
                foreach ($order->getGroups() as $group) {
                    $groupTasks = array_merge($groupTasks, $this->getTasksInGroup($group->getOrders()));
                }
            } else {
                $groupTasks[] = $order;
            }

            return $groupTasks;
        }, []);
    }

    /**
     * Count of in progress tasks
     *
     * @return int
     */
    public function countInProgressTasks(): int
    {
        return count($this->getInProgressTasks());
    }

    /**
     * Get collection of in progress tasks
     *
     * @return array
     */
    public function getInProgressTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) {
            return $task->isInProgress();
        });
    }

    /**
     * @return GroupProgress
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
            new TaskProgressCollection(array_map(function (Task $task) {
                return $task->getTaskProgress();
            }, $this->getTasksInGroup()))
        );
    }

    /**
     * @return string
     */
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
     *
     * @return Status
     */
    public function getStatus(): Status
    {
        if ($this->isCompleted()) {
            if ($this->hasFailure()) {
                return Status::buildFailed();
            }

            return Status::buildSuccess();
        } elseif ($this->isPending()) {
            return Status::buildPending();
        } elseif ($this->isRunning()) {
            return Status::buildRunning();
        }
    }

    /**
     * Has all tasks in group completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->countTasksInGroup() === $this->countCompletedTasks();
    }

    /**
     * Count of orders in group
     *
     * @return int
     */
    public function countTasksInGroup(): int
    {
        return count($this->getTasksInGroup());
    }

    /**
     * Count of completed tasks
     *
     * @return int
     */
    public function countCompletedTasks(): int
    {
        return count($this->getCompletedTasks());
    }

    /**
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) {
            return $task->isCompleted();
        });
    }

    /**
     * At least a task has failed
     *
     * @return bool
     */
    public function hasFailure(): bool
    {
        return $this->countFailedTasks() > 0;
    }

    /**
     * Count of failed tasks
     *
     * @return int
     */
    public function countFailedTasks(): int
    {
        return count($this->getFailedTasks());
    }

    /**
     * Get collection of completed tasks which have failed
     *
     * @return array
     */
    public function getFailedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) {
            return $task->isOnFailure();
        });
    }

    /**
     * No task yet dispatched
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return !$this->isCompleted() && $this->countDispatchedTasks() === 0;
    }

    /**
     * Count of dispatched tasks
     * @return int
     */
    public function countDispatchedTasks(?string $taskType = null): int
    {
        return count($this->getDispatchedTasks($taskType));
    }

    /**
     * Get Collection of dispatched tasks
     * @param string|null $taskType
     * @return array
     */
    public function getDispatchedTasks(?string $taskType = null): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) use ($taskType) {
            if (!is_null($taskType)) {
                return $task->isDispatched() && $task->getType() === $taskType;
            }

            return $task->isDispatched();
        });
    }

    /**
     * At least a task has been dispatched
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return !$this->isPending();
    }

    /**
     * @return Timer
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
     *
     * @return float
     */
    public function getExecutionTime(): float
    {
        $totalExecutionTime = 0;

        if ($this->isPending()) {
            return $totalExecutionTime;
        }

        /**
         * @var Task $order
         */
        foreach ($this->getCompletedTasks() as $order) {
            $totalExecutionTime += $order->getExecutionTime();
        }

        return $totalExecutionTime;
    }

    /**
     * Iterate through tasks and find
     * first started task
     *
     * @return \DateTime|null
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

        /**
         * @var Task $task
         */
        foreach ($this->getTasksInGroup() as $task) {
            /**
             * Ignore task not yet started
             */
            if ($task->isPlanified() ||
                $task->isDispatched() ||
                is_null($task->getStartTime())
            ) {
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
     *
     * @return \DateTime|null
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

        /**
         * @var Task $task
         */
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
     * @return Percentage
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
     *
     * @return int
     */
    public function countSuccessfulTasks(): int
    {
        return count($this->getSuccessfulTasks());
    }

    /**
     * Get collection of successfully completed tasks
     *
     * @return array
     */
    public function getSuccessfulTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function (Task $task) {
            return $task->isSuccess();
        });
    }
}
