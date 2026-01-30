<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\Contract\ProgressBagInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\GroupedTasksProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupedTasksProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\WorkflowProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;

class WorkflowProgressBag implements ProgressBagInterface
{
    private readonly string $workflowId;

    public function __construct(/**
     * @var Group[] $groups
     */
    private readonly array $groups, string $workflowId)
    {
        $this->assertUuid($workflowId);
        $this->workflowId = $workflowId;
    }

    /**
     * @throws InvalidUuidException
     */
    protected function assertUuid(string $uuid)
    {
        (new UuidValidator())->validate($uuid);
    }

    public function hasPendingGroups(): bool
    {
        return $this->getPendingGroups() !== [];
    }

    /**
     * Get collection of planified groups waiting for dispatch
     */
    public function getPendingGroups(): array
    {
        return array_filter($this->groups, fn(Group $group) => $group->getProgressBag()->isPending());
    }

    public function getNextPendingGroup(): Group
    {
        $pendingGroups = $this->getPendingGroups();

        return reset($pendingGroups);
    }

    /**
     * Count of pending groups
     */
    public function countPendingGroups(): int
    {
        return count($this->getPendingGroups());
    }

    /**
     * Count of successful groups
     */
    public function countSuccessfulGroups(): int
    {
        return count($this->getSuccessfulGroups());
    }

    /**
     * Get Collection of successful groups
     */
    public function getSuccessfulGroups(): array
    {
        return array_filter($this->groups, fn(Group $group) => $group->getProgressBag()->isCompleted() && !$group->getProgressBag()->hasFailure());
    }

    /**
     * Count of total planified orders waiting to be dispatched in workflow
     */
    public function countTotalPlanifiedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countPlanifiedTasks();
        }

        return $totalTasks;
    }

    /**
     * Count of total dispatched orders waiting to be dispatched in workflow
     */
    public function countTotalDispatchedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countDispatchedTasks();
        }

        return $totalTasks;
    }

    /**
     * Status of workflow
     * Can be in:
     * - PENDING : no tasks of any group yet dispatched
     * - RUNNING : at least one task of a group has been dispatched
     * - FINISHED : all tasks of all groups have completed
     */
    public function getStatus(): Status
    {
        if ($this->isCompleted()) {
            if ($this->hasFailure()) {
                return Status::buildFailed();
            }

            return Status::buildSuccess();
        }

        if ($this->isRunning()) {
            return Status::buildRunning();
        }

        return Status::buildPending();
    }

    /**
     * Has all tasks in all groups completed
     */
    public function isCompleted(): bool
    {
        return $this->countGroupsInWorkflow() === $this->countCompletedGroups();
    }

    /**
     * Count of group in orders
     */
    public function countGroupsInWorkflow(): int
    {
        return count($this->groups);
    }

    /**
     * Count of completed groups
     */
    public function countCompletedGroups(): int
    {
        return count($this->getCompletedGroups());
    }

    /**
     * Get Collection of completed groups
     */
    public function getCompletedGroups(): array
    {
        return array_filter($this->groups, fn(Group $group) => $group->getProgressBag()->isCompleted());
    }

    /**
     * At least a group tasks has failed
     */
    public function hasFailure(): bool
    {
        return $this->countFailedGroups() > 0;
    }

    /**
     * Count of failed groups
     */
    public function countFailedGroups(): int
    {
        return count($this->getFailedGroups());
    }

    /**
     * Get Collection of failed groups
     */
    public function getFailedGroups(): array
    {
        return array_filter($this->groups, fn(Group $group) => $group->getProgressBag()->isCompleted() && $group->getProgressBag()->hasFailure());
    }

    /**
     * At least one group task has been dispatched
     */
    public function isRunning(): bool
    {
        return !$this->isPending();
    }

    /**
     * No group task yet dispatched
     */
    public function isPending(): bool
    {
        return !$this->isCompleted() && $this->countRunningGroups() === 0;
    }

    /**
     * Count of running groups
     */
    public function countRunningGroups(): int
    {
        return count($this->getRunningGroups());
    }

    /**
     * Get collection of running groups
     */
    public function getRunningGroups(): array
    {
        return array_filter($this->groups, fn(Group $group) => $group->getProgressBag()->isRunning());
    }

    /**
     * Get workflow execution time
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
            if ($group->getProgressBag()->isPending()) {
                continue;
            }
            if (is_null($group->getProgressBag()->getStartedAt())) {
                continue;
            }
            if (is_null($startTime)) {
                $startTime = $group->getProgressBag()->getStartedAt();
            } elseif ($group->getProgressBag()->getStartedAt() < $startTime) {
                $startTime = $group->getProgressBag()->getStartedAt();
            }
        }

        return $startTime;
    }

    /**
     * Iterate through groups and find
     * last completed group
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
            } elseif ($group->getProgressBag()->getFinishedAt() > $finishTime) {
                $finishTime = $group->getProgressBag()->getFinishedAt();
            }
        }

        return $finishTime;
    }

    /**
     * Get percentage progression of workflow
     *
     * @throws InvalidProgressValueException
     */
    public function getPercentage(): Percentage
    {
        $totalOrders = $this->countTotalOrders();

        return Percentage::build(
            Percentage::calculate($this->countTotalCompletedOrders(), $totalOrders),
            Percentage::calculate($this->countTotalSuccessfulOrders(), $totalOrders),
            Percentage::calculate($this->countTotalFailedOrders(), $totalOrders)
        );
    }

    /**
     * Count of total orders in workflow
     */
    public function countTotalOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countTasksInGroup();
        }

        return $totalTasks;
    }

    /**
     * Count of total completed orders in workflow
     */
    public function countTotalCompletedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countCompletedTasks();
        }

        return $totalTasks;
    }

    /**
     * Count of total successfull orders in workflow
     */
    public function countTotalSuccessfulOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countSuccessfulTasks();
        }

        return $totalTasks;
    }

    /**
     * Count of total failed orders in workflow
     */
    public function countTotalFailedOrders(): int
    {
        $totalTasks = 0;

        /**
         * @var Group $group
         */
        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countFailedTasks();
        }

        return $totalTasks;
    }

    /**
     * @return array|Group[]
     */
    public function getGroupInWorkflow()
    {
        return $this->groups;
    }

    public function getGroupsStatus(): Status
    {
        if ($this->countTotalOrders() === $this->countTotalCompletedOrders()) {
            if ($this->countFailedGroups() !== 0) {
                return Status::buildFailed();
            }

            return Status::buildSuccess();
        }

        if ($this->countRunningGroups() !== 0) {
            return Status::buildRunning();
        }

        return Status::buildPending();
    }

    /**
     * @throws InvalidProgressValueException
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public function getWorkflowProgress(): WorkflowProgress
    {
        return WorkflowProgress::fromWorkflowProgressBag($this);
    }

    /**
     * Get summary of all tasks group by task type inside workflow
     *
     * @throws InvalidProgressValueException
     */
    public function getGroupedTasks(): GroupedTasksProgressCollection
    {
        /**
         * Get tasks in group recursively
         */
        $summary = $this->getGroupTasksSummaryRecursively($this->groups);

        $groupedTasks = [];
        foreach ($summary as $type => $detail) {
            if ($detail['failed'] !== 0) {
                $status = Status::buildFailed();
            } elseif ($detail['completed'] === $detail['successful'] && $detail['completed'] === $detail['total']) {
                $status = Status::buildSuccess();
            } elseif ($detail['total'] === $detail['pending']) {
                $status = Status::buildPending();
            } else {
                $status = Status::buildRunning();
            }

            $groupedTasks[$type] = GroupedTasksProgress::build(
                $type,
                $status,
                Summary::build(
                    $detail['total'],
                    $detail['pending'],
                    $detail['dispatched'],
                    $detail['completed'],
                    $detail['successful'],
                    $detail['failed']
                ),
                $detail['uuids']
            );
        }

        return new GroupedTasksProgressCollection($groupedTasks);
    }

    /**
     * Get summary of all tasks in group
     */
    private function getGroupTasksSummaryRecursively(array $groups): array
    {
        $summary = [];

        /** @var Group $group */
        foreach ($groups as $group) {
            foreach ($group->getOrders() as $order) {
                if ($order instanceof Workflow) {
                    $summary = array_merge($summary, $this->getGroupTasksSummaryRecursively($order->getGroups()));
                } else {
                    /**
                     * Order is a Task
                     * Initialize counts for current task type
                     */
                    if (!isset($summary[$order->getType()])) {
                        $summary = $this->initializeDetailsByType($summary, $order);
                    }

                    /*
                     * Update counts for current task type
                     */
                    $summary = $this->updateDetailsByType($summary, $order);
                }
            }
        }

        return $summary;
    }

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
     */
    private function updateDetailsByType(array $summary, Task $task): array
    {
        ++$summary[$task->getType()]['total'];
        $summary[$task->getType()]['uuids'][] = $task->getOrderMessage()->getOrderId();
        switch ($task) {
            case $task->isOnFailure():
                ++$summary[$task->getType()]['failed'];
                ++$summary[$task->getType()]['completed'];
                break;
            case $task->isSuccess():
                ++$summary[$task->getType()]['successful'];
                ++$summary[$task->getType()]['completed'];
                break;
            case $task->isDispatched():
                ++$summary[$task->getType()]['dispatched'];
                break;
            case $task->isCompleted():
                ++$summary[$task->getType()]['completed'];
                break;
            case $task->isPlanified():
                ++$summary[$task->getType()]['pending'];
                break;
        }

        return $summary;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    /**
     * Get all tasks in the workflow
     */
    public function getOrdersInWorkflow(): array
    {
        $tasks = [];

        foreach ($this->groups as $group) {
            $tasks = array_merge($tasks, $group->getOrders());
        }

        return $tasks;
    }
}
