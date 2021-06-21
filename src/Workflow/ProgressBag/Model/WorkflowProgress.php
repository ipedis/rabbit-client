<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Summary\GroupedTasksProgressSummary;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Summary\GroupProgressSummary;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class WorkflowProgress implements \JsonSerializable
{
    /**
     * @var string
     */
    private string $uuid;
    /**
     * @var Status
     */
    private Status $status;
    /**
     * @var Timer
     */
    private Timer $timer;
    /**
     * @var Percentage
     */
    private Percentage $percentage;
    /**
     * @var GroupProgressSummary
     */
    private GroupProgressSummary $groupProgressSummary;
    /**
     * @var GroupedTasksProgressSummary
     */
    private GroupedTasksProgressSummary $groupedTasksSummary;

    /**
     * Workflow constructor.
     * @param string $uuid
     * @param Status $status
     * @param Timer $timer
     * @param Percentage $percentage
     * @param GroupProgressSummary $groupProgressSummary
     * @param GroupedTasksProgressSummary $groupedTasksSummary
     */
    private function __construct(
        string $uuid,
        Status $status,
        Timer $timer,
        Percentage $percentage,
        GroupProgressSummary $groupProgressSummary,
        GroupedTasksProgressSummary $groupedTasksSummary
    ) {
        $this->uuid = $uuid;
        $this->status = $status;
        $this->timer = $timer;
        $this->percentage = $percentage;
        $this->groupProgressSummary = $groupProgressSummary;
        $this->groupedTasksSummary = $groupedTasksSummary;
    }

    /**
     * @param string $uuid
     * @param Status $status
     * @param Timer $timer
     * @param Percentage $percentage
     * @param GroupProgressSummary $groupProgressSummary
     * @param GroupedTasksProgressSummary $groupedTasksSummary
     * @return $this
     */
    public static function build(
        string $uuid,
        Status $status,
        Timer $timer,
        Percentage $percentage,
        GroupProgressSummary $groupProgressSummary,
        GroupedTasksProgressSummary $groupedTasksSummary
    ): self {
        return new self($uuid, $status, $timer, $percentage, $groupProgressSummary, $groupedTasksSummary);
    }

    /**
     * @param WorkflowProgressBag $workflowProgressBag
     * @return WorkflowProgress
     * @throws InvalidProgressValueException
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public static function fromWorkflowProgressBag(WorkflowProgressBag $workflowProgressBag)
    {
        return new self(
            $workflowProgressBag->getWorkflowId(),
            $workflowProgressBag->getStatus(),
            Timer::build(
                $workflowProgressBag->getExecutionTime(),
                $workflowProgressBag->getStartedAt(),
                $workflowProgressBag->getFinishedAt()
            ),
            $workflowProgressBag->getPercentage(),
            GroupProgressSummary::fromWorkflow($workflowProgressBag),
            GroupedTasksProgressSummary::fromWorkflow($workflowProgressBag)
        );
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'status' => $this->getStatus(),
            'timer' => $this->getTimer(),
            'percentage' => $this->getPercentage(),
            'groups' => $this->getGroupProgressSummary(),
            'tasks' => $this->getGroupedTasksSummary()
        ];
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @return Timer
     */
    public function getTimer(): Timer
    {
        return $this->timer;
    }

    /**
     * @return Percentage
     */
    public function getPercentage(): Percentage
    {
        return $this->percentage;
    }

    /**
     * @return GroupProgressSummary
     */
    public function getGroupProgressSummary(): GroupProgressSummary
    {
        return $this->groupProgressSummary;
    }

    /**
     * @return GroupedTasksProgressSummary
     */
    public function getGroupedTasksSummary(): GroupedTasksProgressSummary
    {
        return $this->groupedTasksSummary;
    }

    /**
     * @param WorkflowProgressBag $workflowProgressBag
     * @return Summary
     * @throws InvalidProgressValueException
     */
    private function buildGroupsSummary(WorkflowProgressBag $workflowProgressBag)
    {
        return Summary::build(
            $workflowProgressBag->countGroupsInWorkflow(),
            $workflowProgressBag->countPendingGroups(),
            $workflowProgressBag->countRunningGroups(),
            $workflowProgressBag->countCompletedGroups(),
            $workflowProgressBag->countSuccessfulGroups(),
            $workflowProgressBag->countFailedGroups()
        );
    }
}
