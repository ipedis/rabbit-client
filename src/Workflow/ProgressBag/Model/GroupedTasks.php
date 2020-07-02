<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;

class GroupedTasks implements \JsonSerializable
{
    /**
     * @var string
     */
    private string $type;
    /**
     * @var Status
     */
    private Status $status;
    /**
     * @var Summary
     */
    private Summary $summary;
    /**
     * @var array
     */
    private array $taskUuids;

    private function __construct(string $type, Status $status, Summary $summary, array $taskUuids)
    {
        $this->validateInputs($taskUuids);
        $this->type = $type;
        $this->status = $status;
        $this->summary = $summary;
        $this->taskUuids = $taskUuids;
    }

    /**
     * @param string $type
     * @param Status $status
     * @param Summary $summary
     * @param array $taskUuids
     * @return GroupedTasks
     */
    public static function build(string $type, Status $status, Summary $summary, array $taskUuids): self
    {
        return new self($type, $status, $summary, $taskUuids);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * @return array
     */
    public function getTaskUuids(): array
    {
        return $this->taskUuids;
    }

    /**
     * @return array[]
     */
    public function jsonSerialize()
    {
        return [
            $this->getType() => [
                'type' => $this->getType(),
                'status' => $this->getStatus(),
                'summary' => $this->getSummary(),
                'contain' => $this->getTaskUuids()
            ]
        ];
    }

    /**
     * @param array $taskUuids
     * @throws \Ipedis\Rabbit\Exception\InvalidUuidException
     */
    private function validateInputs(array $taskUuids)
    {
        $validator = new UuidValidator();

        foreach ($taskUuids as $taskUuid) {
            $validator->validate($taskUuid);
        }
    }

}