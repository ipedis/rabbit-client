<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


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
        return $this->countDispatchedOrders() === 0;
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
     * @return string
     */
    public function getStatus(): string
    {
        if ($this->isCompleted()) {
            return self::STATUS_FINISHED;
        } elseif ($this->isRunning()) {
            return self::STATUS_RUNNING;
        } else {
            return self::STATUS_PENDING;
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
     * Get percentage progression of group
     *
     * @return float
     */
    public function getPercentageProgression(): float
    {
        $percentage = ($this->countCompletedOrders() / $this->countOrdersInGroup()) * 100;

        return round($percentage, 2);
    }

    /**
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'status' => $this->getStatus(),
            'percentage_progression' => sprintf('%s %%', $this->getPercentageProgression()),
            'tasks' => [
                'total'         => $this->countOrdersInGroup(),
                'pending'       => $this->countPlanifiedOrders(),
                'dispatched'    => $this->countDispatchedOrders(),
                'completed'     => $this->countCompletedOrders(),
                'successful'    => $this->countSuccessfulOrders(),
                'failed'        => $this->countFailedOrders(),
            ]
        ];
    }
}
