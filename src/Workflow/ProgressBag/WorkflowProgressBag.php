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

    /**
     * @param list<Group> $groups
     */
    public function __construct(
        private readonly array $groups,
        string $workflowId
    ) {
        $this->assertUuid($workflowId);
        $this->workflowId = $workflowId;
    }

    /**
     * @throws InvalidUuidException
     */
    protected function assertUuid(string $uuid): void
    {
        (new UuidValidator())->validate($uuid);
    }

    public function hasPendingGroups(): bool
    {
        return $this->getPendingGroups() !== [];
    }

    /**
     * Get collection of planified groups waiting for dispatch
     *
     * @return list<Group>
     */
    public function getPendingGroups(): array
    {
        return array_values(array_filter($this->groups, fn (Group $group): bool => $group->getProgressBag()->isPending()));
    }

    public function getNextPendingGroup(): Group
    {
        $pendingGroups = $this->getPendingGroups();
        $first = reset($pendingGroups);
        assert($first instanceof Group);

        return $first;
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
     *
     * @return list<Group>
     */
    public function getSuccessfulGroups(): array
    {
        return array_values(array_filter($this->groups, fn (Group $group): bool => $group->getProgressBag()->isCompleted() && !$group->getProgressBag()->hasFailure()));
    }

    /**
     * Count of total planified orders waiting to be dispatched in workflow
     */
    public function countTotalPlanifiedOrders(): int
    {
        $totalTasks = 0;

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
     *
     * @return list<Group>
     */
    public function getCompletedGroups(): array
    {
        return array_values(array_filter($this->groups, fn (Group $group): bool => $group->getProgressBag()->isCompleted()));
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
     *
     * @return list<Group>
     */
    public function getFailedGroups(): array
    {
        return array_values(array_filter($this->groups, fn (Group $group): bool => $group->getProgressBag()->isCompleted() && $group->getProgressBag()->hasFailure()));
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
     *
     * @return list<Group>
     */
    public function getRunningGroups(): array
    {
        return array_values(array_filter($this->groups, fn (Group $group): bool => $group->getProgressBag()->isRunning()));
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

        foreach ($this->groups as $group) {
            /**
             * If for any reason group does not have finish time
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

        foreach ($this->groups as $group) {
            $totalTasks += $group->getProgressBag()->countFailedTasks();
        }

        return $totalTasks;
    }

    /**
     * @return list<Group>
     */
    public function getGroupInWorkflow(): array
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

        /** @var array<string, GroupedTasksProgress> $groupedTasks */
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
     *
     * @param list<Group> $groups
     * @return array<string, array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int, uuids: list<string>}>
     */
    private function getGroupTasksSummaryRecursively(array $groups): array
    {
        /** @var array<string, array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int, uuids: list<string>}> $summary */
        $summary = [];

        foreach ($groups as $group) {
            foreach ($group->getOrders() as $order) {
                if ($order instanceof Workflow) {
                    $subSummary = $this->getGroupTasksSummaryRecursively($order->getGroups());
                    foreach ($subSummary as $type => $subDetail) {
                        if (!isset($summary[$type])) {
                            $summary[$type] = $subDetail;
                        } else {
                            $summary[$type]['total'] += $subDetail['total'];
                            $summary[$type]['pending'] += $subDetail['pending'];
                            $summary[$type]['dispatched'] += $subDetail['dispatched'];
                            $summary[$type]['completed'] += $subDetail['completed'];
                            $summary[$type]['successful'] += $subDetail['successful'];
                            $summary[$type]['failed'] += $subDetail['failed'];
                            $summary[$type]['uuids'] = array_merge($summary[$type]['uuids'], $subDetail['uuids']);
                        }
                    }
                } elseif ($order instanceof Task) {
                    if (!isset($summary[$order->getType()])) {
                        $summary[$order->getType()] = [
                            'total' => 0,
                            'pending' => 0,
                            'dispatched' => 0,
                            'completed' => 0,
                            'successful' => 0,
                            'failed' => 0,
                            'uuids' => [],
                        ];
                    }

                    $summary = $this->updateDetailsByType($summary, $order);
                }
            }
        }

        return $summary;
    }

    /**
     * Append associated array key by 1
     *
     * @param array<string, array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int, uuids: list<string>}> $summary
     * @return array<string, array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int, uuids: list<string>}>
     */
    private function updateDetailsByType(array $summary, Task $task): array
    {
        $type = $task->getType();
        /** @var array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int, uuids: list<string>} $entry */
        $entry = $summary[$type];
        ++$entry['total'];
        $entry['uuids'][] = $task->getOrderMessage()->getOrderId();

        if ($task->isOnFailure()) {
            ++$entry['failed'];
            ++$entry['completed'];
        } elseif ($task->isSuccess()) {
            ++$entry['successful'];
            ++$entry['completed'];
        } elseif ($task->isDispatched()) {
            ++$entry['dispatched'];
        } elseif ($task->isCompleted()) {
            ++$entry['completed'];
        } elseif ($task->isPlanified()) {
            ++$entry['pending'];
        }

        $summary[$type] = $entry;

        return $summary;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    /**
     * Get all tasks in the workflow
     *
     * @return array<string, Task|Workflow>
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
