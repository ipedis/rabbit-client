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

    /**
     * @return TimerType
     */
    public function getTimer()
    {
        return TimerType::build(
            $this->progressBag->getExecutionTime(),
            $this->progressBag->getStartedAt(),
            $this->progressBag->getFinishedAt()
        );
    }

    public function getGroupedTasks()
    {
        return [
            'uuid' => $this->progressBag->getWorkflowId(),
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
        ];
    }

    public function getProgress()
    {
        return $this->progressBag->getPercentage();
    }

    public function getGroups()
    {
        return [
            'uuid' => $this->progressBag->getWorkflowId(),
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
            }, $this->progressBag->getGroupInWorkflow())
        ];
    }

    public function jsonSerialize()
    {
        return [
            'uuid' => $this->progressBag->getWorkflowId(),
            'status' => $this->progressBag->getStatus(),
            'progression' => $this->getProgress(),
            'timer' => $this->getTimer(),
            'groups' => $this->getGroups(),
            'tasks' => $this->getGroupedTasks()
        ];
    }
}
