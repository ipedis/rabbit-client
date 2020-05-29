<?php


namespace Ipedis\Rabbit\DTO\Group;


use Ipedis\Rabbit\DTO\Type\Group\GroupType;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\SummaryType;

class Groups implements \JsonSerializable
{
    /**
     * @var StatusType
     */
    private $status;

    /**
     * @var SummaryType
     */
    private $summary;

    /**
     * @var array
     */
    private $details;

    /**
     * @var ProgressType
     */
    private $progress;

    public function __construct(
        StatusType $status,
        SummaryType $summary,
        ProgressType $progressType ,
        array $details
    ) {
        $this->assertDetails($details);
        $this->status = $status;
        $this->summary = $summary;
        $this->details = $details;
        $this->progress = $progressType;
    }

    /**
     * @return ProgressType
     */
    public function getProgress(): ProgressType
    {
        return $this->progress;
    }

    /**
     * @return StatusType
     */
    public function getStatus(): StatusType
    {
        return $this->status;
    }

    /**
     * @return SummaryType
     */
    public function getSummary(): SummaryType
    {
        return $this->summary;
    }

    /**
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    private function assertDetails(array $details)
    {
        foreach ($details as $detail) {
            if (!$detail instanceof GroupType) {
                throw new \Exception('Element of details should be a groupType');
            }
        }
    }

    /**
     * Get all succesfully completed groups
     * @return array
     */
    public function getSuccessfullGroups()
    {
        return array_filter($this->details, function (GroupType $groupType) {
           return $groupType->getStatus()->isSuccess();
        });
    }

    /**
     * Get all completed groups but with some tasks failed
     * @return array
     */
    public function getFailedGroups()
    {
        return array_filter($this->details, function (GroupType $groupType) {
            return $groupType->getStatus()->isFailed();
        });
    }

    /**
     * Get all groups that have no task running yet
     * @return array
     */
    public function getInPendingGroups()
    {
        return array_filter($this->details, function (GroupType $groupType) {
            return $groupType->getStatus()->isPending();
        });
    }

    /**
     * Get all groups currently running
     */
    public function getRunningGroups()
    {
        return array_filter($this->details, function (GroupType $groupType) {
            return $groupType->getStatus()->isRunning();
        });
    }

    /**
     * Get groups that are already completed (failed + success)
     * @return array
     */
    public function getFinishedGroups()
    {
        return array_merge(
            $this->getSuccessfullGroups(),
            $this->getFailedGroups()
        );
    }

    public function find(string $groupId)
    {
        $group = array_filter($this->details, function (GroupType $groupType) use ($groupId){
            return $groupType->getUuid() === $groupId;
        });

        return $group[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'status' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'details' => $this->getDetails(),
            'progress' => $this->getProgress()
        ];
    }
}
