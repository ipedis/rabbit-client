<?php


namespace Ipedis\Rabbit\DTO\Type\Group;


use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\SummaryType;

class GroupedTaskType implements \JsonSerializable
{
    private $status;
    private $summary;
    private $type;
    private $uuids;

    /**
     * GroupedTaskType constructor.
     * @param StatusType $status
     * @param SummaryType $summary
     * @param string $type
     * @param string[] $uuids
     */
    private function __construct(StatusType $status, SummaryType $summary, string $type, array $uuids)
    {
        $this->status = $status;
        $this->summary = $summary;
        $this->type = $type;
        $this->uuids = $uuids;
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
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array|string[]
     */
    public function getUuids()
    {
        return $this->uuids;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'state' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'type' => $this->getType(),
            'contain' => $this->getUuids()
        ];
    }
}
