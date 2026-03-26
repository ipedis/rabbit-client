<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Summary;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\GroupProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupProgress;

class GroupProgressSummary implements \JsonSerializable
{
    public function __construct(private readonly Status $status, private readonly Summary $summary, private readonly GroupProgressCollection $groupProgressCollection)
    {
    }

    /**
     * @throws InvalidProgressValueException
     */
    public static function fromWorkflow(WorkflowProgressBag $workflowProgressBag): self
    {
        return new self(
            $workflowProgressBag->getGroupsStatus(),
            Summary::build(
                $workflowProgressBag->countGroupsInWorkflow(),
                $workflowProgressBag->countPendingGroups(),
                $workflowProgressBag->countRunningGroups(),
                $workflowProgressBag->countCompletedGroups(),
                $workflowProgressBag->countSuccessfulGroups(),
                $workflowProgressBag->countFailedGroups()
            ),
            new GroupProgressCollection(array_map(fn (Group $group): GroupProgress => $group->getProgressBag()->getGroupProgress(), $workflowProgressBag->getGroupInWorkflow()))
        );
    }

    /**
     * @return array{status: Status, summary: Summary, details: GroupProgressCollection}
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'details' => $this->getGroupProgressCollection()
        ];
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getSummary(): Summary
    {
        return $this->summary;
    }

    public function getGroupProgressCollection(): GroupProgressCollection
    {
        return $this->groupProgressCollection;
    }
}
