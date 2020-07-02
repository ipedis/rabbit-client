<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\DTO\Order\Tasks;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\SummaryType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;
use Ipedis\Rabbit\Workflow\ProgressBag\Contract\ProgressBagInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;

class GroupProgressBag implements ProgressBagInterface
{
    /**
     * Group orders
     *
     * @var $orders
     */
    private $orders = [];

    public function __construct(array $orders)
    {
        $this->orders = $orders;
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
     * Get collection of planified tasks
     * waiting to be dispatched
     *
     * @return array
     */
    public function getPlanifiedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function(Task $task) {
            return $task->isPlanified();
        });
    }

    /**
     * Get Collection of dispatched tasks
     * @return array
     */
    public function getDispatchedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function(Task $task) {
            return $task->isDispatched();
        });
    }

    /**
     * Get collection of in progress tasks
     *
     * @return array
     */
    public function getInProgressTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function(Task $task) {
            return $task->isInProgress();
        });
    }

    /**
     * Get collection of successfully completed tasks
     *
     * @return array
     */
    public function getSuccessfulTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function(Task $task) {
            return $task->isSuccess();
        });
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
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedTasks(): array
    {
        return array_filter($this->getTasksInGroup(), function(Task $task) {
            return $task->isCompleted();
        });
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
     * Count of planified tasks
     *
     * @return int
     */
    public function countPlanifiedTasks(): int
    {
        return count($this->getPlanifiedTasks());
    }

    /**
     * Count of dispatched tasks
     * @return int
     */
    public function countDispatchedTasks(): int
    {
        return count($this->getDispatchedTasks());
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
     * Count of successful tasks
     *
     * @return int
     */
    public function countSuccessfulTasks(): int
    {
        return count($this->getSuccessfulTasks());
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
     * Count of completed tasks
     *
     * @return int
     */
    public function countCompletedTasks(): int
    {
        return count($this->getCompletedTasks());
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
     * At least a task has been dispatched
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return !$this->isPending();
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
     * At least a task has failed
     *
     * @return bool
     */
    public function hasFailure(): bool
    {
        return $this->countFailedTasks() > 0;
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
        }  elseif($this->isPending()) {
            return Status::buildPending();
        } elseif ($this->isRunning()) {
            return Status::buildRunning();
        }
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
        foreach ($this->orders as $task) {
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
            } else if($task->getStartTime() < $startTime) {
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
        foreach ($this->orders as $task) {
            /**
             * If for any reason task does not have finish time
             */
            if (is_null($task->getFinishedTime())) {
                continue;
            }

            if (is_null($finishTime)) {
                $finishTime = $task->getFinishedTime();
            } else if($task->getFinishedTime() > $finishTime) {
                $finishTime = $task->getFinishedTime();
            }
        }

        return $finishTime;

    }

    /**
     * Get percentage progression of group
     *
     * @return Percentage
     * @throws \Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException
     */
    public function getPercentage(): Percentage
    {
        return Percentage::build(
            (100 * $this->countCompletedTasks())/ $this->countTasksInGroup(),
            (100 * $this->countSuccessfulTasks())/ $this->countTasksInGroup(),
            (100 * $this->countFailedTasks())/ $this->countTasksInGroup()
        );
    }

    /**
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'status' => $this->getStatus(),
            'percentage' => $this->getPercentage(),
            'timer' => TimerType::build($this->getExecutionTime(), $this->getStartedAt(), $this->getFinishedAt()),
            'tasks' => [
                'state' => $this->getStatus(),
                'summary' => $this->getGlobalSummary(),
                'types' => $this->getGroupedTasksSummary()
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGlobalSummary(): SummaryType
    {
        return SummaryType::build(
            $this->countTasksInGroup(),
            $this->countPlanifiedTasks(),
            $this->countDispatchedTasks(),
            $this->countCompletedTasks(),
            $this->countSuccessfulTasks(),
            $this->countFailedTasks()
        );
    }

    /**
     * Create an array of task grouped by type
     * @return SummaryType[]
     */
    public function getGroupedTasksSummary()
    {
        $summary = [];
        foreach ($this->orders as $task) {
            /*
             * initialize count of task of this type
             */
            if (!isset($summary[$task->getType()])) {
                $summary = $this->initializeDetailsByType($summary, $task);
            }
            /*
             * Update counts by type
             */
            $summary = $this->updateDetailsByType($summary, $task);
        }

        return array_map(function ($type, $detail) {
            return [
                $type => [
                    'type' => $type,
                    'summary' => SummaryType::build(
                        $detail['total'],
                        $detail['pending'],
                        $detail['dispatched'],
                        $detail['completed'],
                        $detail['successful'],
                        $detail['failed']
                    ),
                    'contain' => $detail['uuids']
                ]
            ];
        },array_keys($summary), $summary);
    }

    /**
     * @param array $summary
     * @param Task $task
     * @return array
     */
    private function initializeDetailsByType(array $summary, Task $task): array
    {
        if (isset($summary[$task->getType()])) {
            return $summary;
        }
        $summary[$task->getType()] = [
            'total' => 0,
            'pending' => 0,
            'dispatched' => 0,
            'completed' => 0,
            'successful' => 0,
            'failed' => 0
        ];

        return $summary;
    }

    /**
     * Append associated array key by 1
     * @param array $summary
     * @param Task $task
     * @return array
     */
    private function updateDetailsByType(array $summary, Task $task): array
    {
        $summary[$task->getType()]['total'] ++;
        $summary[$task->getType()]['uuids'][] = $task->getOrderMessage()->getOrderId();
        switch ($task) {
            case $task->isOnFailure():
                $summary[$task->getType()]['failed'] ++;
                break;
            case $task->isSuccess():
                $summary[$task->getType()]['successful'] ++;
                break;
            case $task->isDispatched():
                $summary[$task->getType()]['dispatched'] ++;
                break;
            case $task->isCompleted():
                $summary[$task->getType()]['completed'] ++;
                break;
            case $task->isPlanified():
                $summary[$task->getType()]['pending'] ++;
                break;
        }

        return $summary;
    }

    public function getOrders(): Tasks
    {
        return new Tasks(
            array_map(function (Task $task) {
                return TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    $task->getType(),
                    $task->getStatusType(),
                    $task->getTimer()
                );
            }, $this->orders), $this->getPercentage()
        );
    }
}
