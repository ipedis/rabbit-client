<?php


namespace Ipedis\Rabbit\DTO\Type\Workflow;


use Ipedis\Rabbit\DTO\Type\SummaryType;
use Ipedis\Rabbit\DTO\Type\TimerType;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;

class WorkflowType implements \JsonSerializable
{
    private $progressBag;

    private function __construct(WorkflowProgressBag $workflowProgressBag)
    {
        $this->progressBag = $workflowProgressBag;
    }

    public static function buildSummary(WorkflowProgressBag $workflowProgressBag)
    {
        return new self($workflowProgressBag);
    }

    public function jsonSerialize()
    {
        return [
            'status' => $this->progressBag->getStatus(),
            'progression' => $this->progressBag->getPercentage(),
            'timer' => TimerType::build(
                $this->progressBag->getExecutionTime(),
                $this->progressBag->getStartedAt(),
                $this->progressBag->getFinishedAt()
            ),
            'groups' => [
                'state' => $this->progressBag->getGroupsState(),
                'summary' => SummaryType::build(
                    $this->progressBag->countGroupsInWorkflow(),
                    $this->progressBag->countPendingGroups(),
                    $this->progressBag->countRunningGroups(),
                    $this->progressBag->countCompletedGroups(),
                    $this->progressBag->countSuccessfulGroups(),
                    $this->progressBag->countFailedGroups()
                ),
                'details' => array_map(function (Group $group) {
                    return $group->getDetail();
                }, $this->progressBag->getGroups())
            ],
            'tasks' => [
                'state' => $this->progressBag->getStatus(),
                'summary' => SummaryType::build(
                    $this->progressBag->countTotalOrders(),
                    $this->progressBag->countTotalPlanifiedOrders(),
                    $this->progressBag->countTotalDispatchedOrders(),
                    $this->progressBag->countTotalCompletedOrders(),
                    $this->progressBag->countTotalSuccessfulOrders(),
                    $this->progressBag->countTotalFailedOrders()
                ),
                'types' => $this->progressBag->getGroupedTasksSummary()
            ]
        ];
    }
}
