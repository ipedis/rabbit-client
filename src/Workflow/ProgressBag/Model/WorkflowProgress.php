<?php

declare(strict_types=1);

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
     * Workflow constructor.
     */
    private function __construct(private readonly string $uuid, private readonly Status $status, private readonly Timer $timer, private readonly Percentage $percentage, private readonly GroupProgressSummary $groupProgressSummary, private readonly GroupedTasksProgressSummary $groupedTasksSummary)
    {
    }

    /**
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
     * @throws InvalidProgressValueException
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public static function fromWorkflowProgressBag(WorkflowProgressBag $workflowProgressBag): self
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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getTimer(): Timer
    {
        return $this->timer;
    }

    public function getPercentage(): Percentage
    {
        return $this->percentage;
    }

    public function getGroupProgressSummary(): GroupProgressSummary
    {
        return $this->groupProgressSummary;
    }

    public function getGroupedTasksSummary(): GroupedTasksProgressSummary
    {
        return $this->groupedTasksSummary;
    }
}
