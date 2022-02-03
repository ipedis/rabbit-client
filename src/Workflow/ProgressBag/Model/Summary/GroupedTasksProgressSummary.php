<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Summary;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\GroupedTasksProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupedTasksProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class GroupedTasksProgressSummary implements \JsonSerializable
{
    /**
     * @var Status
     */
    private Status $status;
    /**
     * @var Summary
     */
    private Summary $summary;
    /**
     * @var GroupedTasksProgressCollection
     */
    private GroupedTasksProgressCollection $groupedTasksCollection;

    /**
     * GroupedTasksProgressSummary constructor.
     * @param Status $status
     * @param Summary $summary
     * @param GroupedTasksProgressCollection $groupedTasksCollection
     */
    public function __construct(Status $status, Summary $summary, GroupedTasksProgressCollection $groupedTasksCollection)
    {
        $this->status = $status;
        $this->summary = $summary;
        $this->groupedTasksCollection = $groupedTasksCollection;
    }

    /**
     * @param WorkflowProgressBag $workflowProgressBag
     * @return GroupedTasksProgressSummary
     * @throws InvalidProgressValueException
     */
    public static function fromWorkflow(WorkflowProgressBag $workflowProgressBag): self
    {
        $groupedTasks = $workflowProgressBag->getGroupedTasks();
        // initialize summary values
        $total = 0;
        $pending = 0;
        $dispatched = 0;
        $completed = 0;
        $success = 0;
        $failed = 0;

        /**
         * Prepare summary values
         * @var GroupedTasksProgress $groupedTask
         */
        foreach ($groupedTasks as $groupedTask) {
            $total += $groupedTask->getSummary()->getTotal();
            $pending += $groupedTask->getSummary()->getPending();
            $dispatched += $groupedTask->getSummary()->getDispatched();
            $completed += $groupedTask->getSummary()->getCompleted();
            $success += $groupedTask->getSummary()->getSuccessful();
            $failed += $groupedTask->getSummary()->getFailed();
        }

        /*
         * Prepare status
         */
        if ($total === $completed) {
            if ($failed > 0) {
                $status = Status::buildFailed();
            } else {
                $status = Status::buildSuccess();
            }
        } elseif ($pending > 0) {
            $status = Status::buildPending();
        } else {
            $status = Status::buildRunning();
        }

        return new self($status, Summary::build($total, $pending, $dispatched, $completed, $success, $failed), $groupedTasks);
    }

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'tasks' => $this->getGroupedTasksCollection()
        ];
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @return Summary
     */
    public function getSummary(): Summary
    {
        return $this->summary;
    }

    /**
     * @return GroupedTasksProgressCollection
     */
    public function getGroupedTasksCollection(): GroupedTasksProgressCollection
    {
        return $this->groupedTasksCollection;
    }
}
