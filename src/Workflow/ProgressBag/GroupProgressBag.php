<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\SummaryType;
use Ipedis\Rabbit\DTO\Type\Task\TasksType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;
use Ipedis\Rabbit\Workflow\Task;

class GroupProgressBag implements ProgressBagInterface
{
    /**
     * Group orders
     *
     * @var Task[] $tasks
     */
    private $tasks = [];

    public function __construct(array $tasks)
    {
        $this->tasks= $tasks;
    }

    /**
     * Get collection of planified tasks
     * waiting to be dispatched
     *
     * @return array
     */
    public function getPlanifiedOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isPlanified();
        });
    }

    /**
     * Get Collection of dispatched tasks
     * @return array
     */
    public function getDispatchedOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isDispatched();
        });
    }

    /**
     * Get collection of in progress orders
     *
     * @return array
     */
    public function getInProgressOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isInProgress();
        });
    }

    /**
     * Get collection of successfully completed orders
     *
     * @return array
     */
    public function getSuccessfulOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isSuccess();
        });
    }

    /**
     * Get collection of completed orders which have failed
     *
     * @return array
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->tasks, function (Task $task) {
            return $task->isOnFailure();
        });
    }

    /**
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isCompleted();
        });
    }

    /**
     * Count of orders in group
     * @return int
     */
    public function countOrdersInGroup(): int
    {
        return count($this->tasks);
    }

    /**
     * Count of planified orders
     *
     * @return int
     */
    public function countPlanifiedOrders(): int
    {
        return count($this->getPlanifiedOrders());
    }

    /**
     * Count of dispatched orders
     * @return int
     */
    public function countDispatchedOrders(): int
    {
        return count($this->getDispatchedOrders());
    }

    /**
     * Count of in progress orders
     *
     * @return int
     */
    public function countInProgressOrders(): int
    {
        return count($this->getInProgressOrders());
    }

    /**
     * Count of successful orders
     *
     * @return int
     */
    public function countSuccessfulOrders(): int
    {
        return count($this->getSuccessfulOrders());
    }

    /**
     * Count of failed orders
     *
     * @return int
     */
    public function countFailedOrders(): int
    {
        return count($this->getFailedOrders());
    }

    /**
     * Count of completed orders
     *
     * @return int
     */
    public function countCompletedOrders(): int
    {
        return count($this->getCompletedOrders());
    }

    /**
     * No task yet dispatched
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return !$this->isCompleted() && $this->countDispatchedOrders() === 0;
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
        return $this->countOrdersInGroup() === $this->countCompletedOrders();
    }

    /**
     * At least a task has failed
     *
     * @return bool
     */
    public function hasFailure(): bool
    {
        return $this->countFailedOrders() > 0;
    }

    /**
     * Status of group.
     * Can be in:
     * - PENDING : no tasks yet dispatched
     * - RUNNING : at least one task has been dispatched
     * - FINISHED : all tasks in group have completed
     *
     * @return StatusType
     */
    public function getStatus(): StatusType
    {
        if ($this->isCompleted()) {
            if ($this->hasFailure()) {
                return StatusType::buildFailed();
            }

            return StatusType::buildSuccess();
        }  elseif($this->isPending()) {
            return StatusType::buildPending();
        } elseif ($this->isRunning()) {
            return StatusType::buildRunning();
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
        foreach ($this->getCompletedOrders() as $order) {
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
        foreach ($this->tasks as $task) {
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
        foreach ($this->tasks as $task) {
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
     * @return ProgressType
     */
    public function getPercentage(): ProgressType
    {
        return ProgressType::build(
            (100 * $this->countCompletedOrders())/ $this->countOrdersInGroup(),
            (100 * $this->countSuccessfulOrders())/ $this->countOrdersInGroup(),
            (100 * $this->countFailedOrders())/ $this->countOrdersInGroup()
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
            $this->countOrdersInGroup(),
            $this->countPlanifiedOrders(),
            $this->countDispatchedOrders(),
            $this->countCompletedOrders(),
            $this->countSuccessfulOrders(),
            $this->countFailedOrders()
        );
    }

    /**
     * Create an array of task grouped by type
     * @return SummaryType[]
     */
    public function getGroupedTasksSummary()
    {
        $summary = [];
        foreach ($this->tasks as $task) {
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

    public function getTasks(): TasksType
    {
        return new TasksType(
            array_map(function (Task $task) {
                return TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    $task->getType(),
                    $task->getStatusType(),
                    $task->getTimer()
                );
            }, $this->tasks), $this->getPercentage()
        );
    }
}
