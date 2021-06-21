<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Summary;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\GroupProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\TaskProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class GroupProgressSummary implements \JsonSerializable
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
     * @var GroupProgressCollection
     */
    private GroupProgressCollection $groupProgressCollection;

    public function __construct(Status $status, Summary $summary, GroupProgressCollection $groupProgressCollection)
    {
        $this->status = $status;
        $this->summary = $summary;
        $this->groupProgressCollection = $groupProgressCollection;
    }

    /**
     * @param WorkflowProgressBag $workflowProgressBag
     * @return GroupProgressSummary
     * @throws InvalidProgressValueException
     */
    public static function fromWorkflow(WorkflowProgressBag $workflowProgressBag)
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
            new GroupProgressCollection(array_map(function (Group $group) {
                return $group->getProgressBag()->getGroupProgress();
            }, $workflowProgressBag->getGroupInWorkflow()))
        );
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
     * @return GroupProgressCollection
     */
    public function getGroupProgressCollection(): GroupProgressCollection
    {
        return $this->groupProgressCollection;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'details' => $this->getGroupProgressCollection()
        ];
    }
}
