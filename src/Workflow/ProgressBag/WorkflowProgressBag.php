<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\DTO\Type\Group\GroupsType;
use Ipedis\Rabbit\DTO\Type\Group\GroupType;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\SummaryType;
use Ipedis\Rabbit\DTO\Type\Task\TasksType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\Workflow\WorkflowType;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Task;

class WorkflowProgressBag implements ProgressBagInterface
{
    /**
     * @var Group[] $groups
     */
    private $groups;

    public function __construct(array $groups)
    {
        $this->groups = $groups;
    }

    /**
     * Get collection of planified groups waiting for dispatch
     *
     * @return array
     */
    public function getPendingGroups(): array
    {
        return array_filter($this->groups, function (Group $group) {
           return $group->getProgressBag()->isPending();
        });
    }

    /**
     * Get collection of running groups
     *
     * @return array
     */
    public function getRunningGroups(): array
    {
        return array_filter($this->groups, function (Group $group) {
            return $group->getProgressBag()->isRunning();
        });
    }

    /**
     * Get Collection of completed groups
     *
     * @return array
     */
    public function getCompletedGroups(): array
    {
        return array_filter($this->groups, function (Group $group) {
            return $group->getProgressBag()->isCompleted();
        });
    }

    /**
     * Get Collection of failed groups
     *
     * @return array
     */
    public function getFailedGroups(): array
    {
        return array_filter($this->groups, function (Group $group) {
            return $group->getProgressBag()->hasFailure();
        });
    }

    /**
     * Get Collection of successful groups
     *
     * @return array
     */
    public function getSuccessfulGroups(): array
    {
        return array_filter($this->groups, function (Group $group) {
            return ($group->getProgressBag()->isCompleted() && !$group->getProgressBag()->hasFailure());
        });
    }

    /**
     * Count of group in orders
     *
     * @return int
     */
    public function countGroupsInWorkflow(): int
    {
        return count($this->groups);
    }

    /**
     * Count of pending groups
     *
     * @return int
     */
    public function countPendingGroups(): int
    {
        return count($this->getPendingGroups());
    }

    /**
     * Count of running groups
     *
     * @return int
     */
    public function countRunningGroups(): int
    {
        return count($this->getRunningGroups());
    }

    /**
     * Count of completed groups
     *
     * @return int
     */
    public function countCompletedGroups(): int
    {
        return count($this->getCompletedGroups());
    }

    /**
     * Count of successful groups
     *
     * @return int
     */
    public function countSuccessfulGroups(): int
    {
        return count($this->getSuccessfulGroups());
    }

    /**
     * Count of failed groups
     *
     * @return int
     */
    public function countFailedGroups(): int
    {
        return count($this->getFailedGroups());
    }

    /**
     * Count of total orders in workflow
     *
     * @return int
     */
    public function countTotalOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countOrdersInGroup();
        }

        return $totalTasks;
    }

    /**
     * Count of total planified orders waiting to be dispatched in workflow
     *
     * @return int
     */
    public function countTotalPlanifiedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countPlanifiedOrders();
        }

        return $totalTasks;
    }

    /**
     * Count of total dispatched orders waiting to be dispatched in workflow
     *
     * @return int
     */
    public function countTotalDispatchedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countDispatchedOrders();
        }

        return $totalTasks;
    }

    /**
     * Count of total completed orders in workflow
     * @return int
     */
    public function countTotalCompletedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countCompletedOrders();
        }

        return $totalTasks;
    }

    /**
     * Count of total successfull orders in workflow
     *
     * @return int
     */
    public function countTotalSuccessfulOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countSuccessfulOrders();
        }

        return $totalTasks;
    }

    /**
     * Count of total failed orders in workflow
     *
     * @return int
     */
    public function countTotalFailedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countFailedOrders();
        }

        return $totalTasks;
    }

    /**
     * No group task yet dispatched
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return !$this->isCompleted() && $this->countRunningGroups() === 0;
    }

    /**
     * At least one group task has been dispatched
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return !$this->isPending();
    }

    /**
     * Has all tasks in all groups completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->countGroupsInWorkflow() === $this->countCompletedGroups();
    }

    /**
     * At least a group tasks has failed
     *
     * @return bool
     */
    public function hasFailure(): bool
    {
        return $this->countFailedGroups() > 0;
    }

    /**
     * Status of workflow
     * Can be in:
     * - PENDING : no tasks of any group yet dispatched
     * - RUNNING : at least one task of a group has been dispatched
     * - FINISHED : all tasks of all groups have completed
     * @return StatusType
     */
    public function getStatus(): StatusType
    {
        if ($this->isCompleted()) {
            if ($this->hasFailure()) {
                return StatusType::buildFailed();
            }

            return StatusType::buildSuccess();
        } elseif ($this->isRunning()) {
            return StatusType::buildRunning();
        }
        return StatusType::buildPending();
    }

    /**
     * Get workflow execution time
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
         * @var Group $group
         */
        foreach ($this->getCompletedGroups() as $group) {
            $totalExecutionTime += $group->getProgressBag()->getExecutionTime();
        }

        return $totalExecutionTime;
    }

    /**
     * Iterate through groups and find
     * first started group
     *
     * @return \DateTime|null
     */
    public function getStartedAt(): ?\DateTime
    {
        $startTime = null;

        /**
         * No tasks of current workflow yet dispatched
         */
        if ($this->isPending()) {
            return $startTime;
        }

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            /**
             * Ignore group not yet started
             */
            if (
                $group->getProgressBag()->isPending() ||
                is_null($group->getProgressBag()->getStartedAt())
            ) {
                continue;
            }

            if (is_null($startTime)) {
                $startTime = $group->getProgressBag()->getStartedAt();
            } else if($group->getProgressBag()->getStartedAt() < $startTime) {
                $startTime = $group->getProgressBag()->getStartedAt();
            }
        }

        return $startTime;
    }

    /**
     * Iterate through groups and find
     * last completed group
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
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            /**
             * If for any reason grou[ does not have finish time
             */
            if (is_null($group->getProgressBag()->getFinishedAt())) {
                continue;
            }

            if (is_null($finishTime)) {
                $finishTime = $group->getProgressBag()->getFinishedAt();
            } else if($group->getProgressBag()->getFinishedAt() > $finishTime) {
                $finishTime = $group->getProgressBag()->getFinishedAt();
            }
        }

        return $finishTime;
    }

    /**
     * Get percentage progression of workflow
     *
     * @return ProgressType
     */
    public function getPercentage(): ProgressType
    {
        return ProgressType::build(
            (100 * $this->countTotalCompletedOrders())/ $this->countTotalOrders(),
            (100 * $this->countTotalSuccessfulOrders())/ $this->countTotalOrders(),
            (100 * $this->countTotalFailedOrders())/ $this->countTotalOrders()
        );
    }

    /**
     * @return array|Group[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    public function getGroupsState()
    {
        if ($this->countFailedGroups() !== 0) {
            return StatusType::buildFailed();
        }

        if ($this->countRunningGroups() !== 0) {
            return StatusType::buildRunning();
        }

        if ($this->countPendingGroups() === $this->countGroupsInWorkflow()) {
            return StatusType::buildPending();
        }

        return StatusType::buildSuccess();
    }

    /**
     * @return WorkflowType
     */
    public function getSummary(): WorkflowType
    {
        return (WorkflowType::buildSummary($this));
    }

    public function getGroupedTasksSummary()
    {
        $summary = [];
        /** @var Group $group */
        foreach ($this->groups as $group) {
            foreach ($group->getTasks() as $task) {
                if (!isset($summary[$task->getType()])) {
                    /*
                     * Initialize counts for current task type
                     */
                    $summary = $this->initializeDetailsByType($summary, $task);
                }
                /*
                 * Update counts for current task type
                 */
                $summary = $this->updateDetailsByType($summary, $task);
            }
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
        }, array_keys($summary),$summary);

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
                $summary[$task->getType()]['completed'] ++;
                break;
            case $task->isSuccess():
                $summary[$task->getType()]['successful'] ++;
                $summary[$task->getType()]['completed'] ++;
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
        $tasks = [];
        foreach ($this->groups as $group) {
            foreach ($group->getTasks() as $task) {
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    $task->getType(),
                    $task->getStatusType(),
                    $task->getTimer()
                );
            }
        }

        return new TasksType($tasks, $this->getPercentage());
    }

    /**
     * Get groups details
     * @return GroupsType
     */
    public function getGroupsSummary(): GroupsType
    {
        $status = $this->getStatus();
        $summary = SummaryType::build(
            $this->countGroupsInWorkflow(),
            $this->countPendingGroups(),
            $this->countRunningGroups(),
            $this->countCompletedGroups(),
            $this->countSuccessfulGroups(),
            $this->countFailedGroups()
        );

        $groups = array_map(function (Group $group) {
            return GroupType::build(
                $group->getGroupId(),
                $group->getStatus(),
                $group->getTimer(),
                $group->getPercentage(),
                array_map(function (Task $task) {
                    return $task->getSummary();
                }, $group->getTasks())
            );
        }, $this->groups);

        return new GroupsType($status, $summary, $this->getPercentage(),$groups);
    }

    /**
     * Get all tasks in the workflow
     * @return array
     */
    public function getOrdersInWorkflow()
    {
        $tasks = [];

        foreach ($this->groups as $group) {
            $tasks = array_merge($tasks, $group->getTasks());
        }

        return $tasks;
    }
}
