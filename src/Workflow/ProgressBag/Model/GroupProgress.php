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
    private readonly TaskProgressCollection $tasks;

    /**
     * Group constructor.
     * @throws InvalidUuidException
     */
    private function __construct(string $uuid, private readonly Status $status, private readonly Timer $timer, private readonly Percentage $percentage, TaskProgressCollection $tasks)
    {
        $this->validateInputs($uuid, $tasks);
        $this->uuid = $uuid;
        $this->tasks = $tasks;
    }

    /**
     * @throws InvalidUuidException
     */
    private function validateInputs(string $uuid, TaskProgressCollection $taskCollection): void
    {
        $this->assertUuid($uuid);
        $this->assertTasks($taskCollection);
    }

    /**
     * @throws InvalidUuidException
     */
    private function assertUuid(string $uuid): void
    {
        (new UuidValidator())->validate($uuid);
    }

    private function assertTasks(TaskProgressCollection $tasks): void
    {
        foreach ($tasks as $task) {
            if (!$task instanceof TaskProgress) {
                throw new \InvalidArgumentException(sprintf('Object of %s expected.', TaskProgress::class));
            }
        }
    }

    /**
     * @throws InvalidUuidException
     */
    public static function build(string $uuid, Status $status, Timer $timer, Percentage $percentage, TaskProgressCollection $tasks): self
    {
        return new self($uuid, $status, $timer, $percentage, $tasks);
    }

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
