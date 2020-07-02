<?php
namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection\TaskCollection;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class Group implements \JsonSerializable
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
     * @var TaskCollection
     */
    private TaskCollection $tasks;
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
     * @param TaskCollection $tasks
     * @throws \Ipedis\Rabbit\Exception\InvalidUuidException
     */
    private function __construct(string $uuid, Status $status, Timer $timer, Percentage $percentage, TaskCollection $tasks)
    {
        $this->validateInputs($uuid, $tasks);
        $this->uuid = $uuid;
        $this->status = $status;
        $this->timer = $timer;
        $this->tasks = $tasks;
        $this->percentage = $percentage;
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
     * @return TaskCollection
     */
    public function getTasks(): TaskCollection
    {
        return $this->tasks;
    }

    public function jsonSerialize()
    {
        return [
            'uuid'          => $this->getUuid(),
            'status'        => $this->getStatus(),
            'timer'         => $this->getTimer(),
            'percentage'    => $this->getPercentage(),
            'tasks'         => $this->getTasks()
        ];
    }

    /**
     * @param string $uuid
     * @param TaskCollection $taskCollection
     * @throws \Ipedis\Rabbit\Exception\InvalidUuidException
     */
    private function validateInputs(string $uuid, TaskCollection $taskCollection)
    {
        $this->assertUuid($uuid);
        $this->assertTasks($taskCollection);
    }

    /**
     * @param string $uuid
     * @throws \Ipedis\Rabbit\Exception\InvalidUuidException
     */
    private function assertUuid(string $uuid)
    {
        (new UuidValidator())->validate($uuid);
    }

    private function assertTasks(TaskCollection $tasks)
    {
        foreach ($tasks as $task) {
            if (!$task instanceof Task) {
                throw new \InvalidArgumentException(sprintf('Object of %s expected.', Task::class));
            }
        }
    }
}