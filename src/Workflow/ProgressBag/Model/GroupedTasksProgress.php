<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Summary;

class GroupedTasksProgress implements \JsonSerializable
{
    private readonly array $taskUuids;

    private function __construct(private readonly string $type, private readonly Status $status, private readonly Summary $summary, array $taskUuids)
    {
        $this->validateInputs($taskUuids);
        $this->taskUuids = $taskUuids;
    }

    /**
     * @throws \Ipedis\Rabbit\Exception\InvalidUuidException
     */
    private function validateInputs(array $taskUuids): void
    {
        $uuidValidator = new UuidValidator();

        foreach ($taskUuids as $taskUuid) {
            $uuidValidator->validate($taskUuid);
        }
    }

    public static function build(string $type, Status $status, Summary $summary, array $taskUuids): self
    {
        return new self($type, $status, $summary, $taskUuids);
    }

    /**
     * @return array[]
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'summary' => $this->getSummary(),
            'contain' => $this->getTaskUuids()
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getSummary(): Summary
    {
        return $this->summary;
    }

    public function getTaskUuids(): array
    {
        return $this->taskUuids;
    }
}
