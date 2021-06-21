<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\TaskProgressCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class GroupProgress implements \JsonSerializable
{
    private string $uuid;
    /**
     * @var Status
     */
    private Status $status;
    /**
     * @var Timer
     */
    private Timer $timer;
    /**
     * @var TaskProgressCollection
     */
    private TaskProgressCollection $tasks;
    /**
     * @var Percentage
     */
    private Percentage $percentage;

    /**
     * Group constructor.
     * @param string $uuid
     * @param Status $status
     * @param Timer $timer
     * @param Percentage $percentage
     * @param TaskProgressCollection $tasks
     * @throws InvalidUuidException
     */
    private function __construct(string $uuid, Status $status, Timer $timer, Percentage $percentage, TaskProgressCollection $tasks)
    {
        $this->validateInputs($uuid, $tasks);
        $this->uuid = $uuid;
        $this->status = $status;
        $this->timer = $timer;
        $this->tasks = $tasks;
        $this->percentage = $percentage;
    }

    /**
     * @param string $uuid
     * @param TaskProgressCollection $taskCollection
     * @throws InvalidUuidException
     */
    private function validateInputs(string $uuid, TaskProgressCollection $taskCollection)
    {
        $this->assertUuid($uuid);
        $this->assertTasks($taskCollection);
    }

    /**
     * @param string $uuid
     * @throws InvalidUuidException
     */
    private function assertUuid(string $uuid)
    {
        (new UuidValidator())->validate($uuid);
    }

    private function assertTasks(TaskProgressCollection $tasks)
    {
        foreach ($tasks as $task) {
            if (!$task instanceof TaskProgress) {
                throw new \InvalidArgumentException(sprintf('Object of %s expected.', TaskProgress::class));
            }
        }
    }

    /**
     * @param string $uuid
     * @param Status $status
     * @param Timer $timer
     * @param Percentage $percentage
     * @param TaskProgressCollection $tasks
     * @return GroupProgress
     * @throws InvalidUuidException
     */
    public static function build(string $uuid, Status $status, Timer $timer, Percentage $percentage, TaskProgressCollection $tasks): self
    {
        return new self($uuid, $status, $timer, $percentage, $tasks);
    }

    public function jsonSerialize()
    {
        return [
            'uuid' => $this->getUuid(),
            'status' => $this->getStatus(),
            'timer' => $this->getTimer(),
            'percentage' => $this->getPercentage(),
            'tasks' => $this->getTasks()
        ];
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @return Timer
     */
    public function getTimer(): Timer
    {
        return $this->timer;
    }

    /**
     * @return Percentage
     */
    public function getPercentage(): Percentage
    {
        return $this->percentage;
    }

    /**
     * @return TaskProgressCollection
     */
    public function getTasks(): TaskProgressCollection
    {
        return $this->tasks;
    }
}
