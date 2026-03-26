<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\TaskProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class GroupProgress implements \JsonSerializable
{
    private readonly string $uuid;

    /**
     * Group constructor.
     * @throws InvalidUuidException
     */
    private function __construct(string $uuid, private readonly Status $status, private readonly Timer $timer, private readonly Percentage $percentage, private readonly TaskProgressCollection $tasks)
    {
        $this->validateInputs($uuid);
        $this->uuid = $uuid;
    }

    /**
     * @throws InvalidUuidException
     */
    private function validateInputs(string $uuid): void
    {
        $this->assertUuid($uuid);
        $this->assertTasks();
    }

    /**
     * @throws InvalidUuidException
     */
    private function assertUuid(string $uuid): void
    {
        (new UuidValidator())->validate($uuid);
    }

    private function assertTasks(): void
    {
    }

    /**
     * @throws InvalidUuidException
     */
    public static function build(string $uuid, Status $status, Timer $timer, Percentage $percentage, TaskProgressCollection $tasks): self
    {
        return new self($uuid, $status, $timer, $percentage, $tasks);
    }

    /**
     * @return array{uuid: string, status: Status, timer: Timer, percentage: Percentage, tasks: TaskProgressCollection}
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'status' => $this->getStatus(),
            'timer' => $this->getTimer(),
            'percentage' => $this->getPercentage(),
            'tasks' => $this->getTasks()
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

    public function getTasks(): TaskProgressCollection
    {
        return $this->tasks;
    }
}
