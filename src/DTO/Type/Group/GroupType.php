<?php


namespace Ipedis\Rabbit\DTO\Type\Group;


use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;

class GroupType implements \JsonSerializable
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var StatusType
     */
    private $status;

    /**
     * @var TimerType
     */
    private $timer;

    /**
     * @var ProgressType
     */
    private $percentage;

    /**
     * @var array|TaskType[]
     */
    private $tasks;

    /**
     * GroupType constructor.
     * @param string $uuid
     * @param StatusType $status
     * @param TimerType $timer
     * @param ProgressType $percentage
     * @param TaskType[] $tasks
     */
    private function __construct(
        string $uuid,
        StatusType $status,
        TimerType $timer,
        ProgressType $percentage,
        array $tasks
    ) {
        $this->uuid = $uuid;
        $this->status = $status;
        $this->timer = $timer;
        $this->percentage = $percentage;
        $this->tasks = $tasks;
    }

    public static function build(
        string $uuid,
        StatusType $status,
        TimerType $timer,
        ProgressType $percentage,
        array $tasks
    ) {
        return new self($uuid, $status, $timer, $percentage, $tasks);
    }
    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return StatusType
     */
    public function getStatus(): StatusType
    {
        return $this->status;
    }

    /**
     * @return TimerType
     */
    public function getTimer(): TimerType
    {
        return $this->timer;
    }

    /**
     * @return ProgressType
     */
    public function getPercentage(): ProgressType
    {
        return $this->percentage;
    }

    /**
     * @return array|TaskType[]
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'uuid' => $this->getUuid(),
            'state' => $this->getStatus(),
            'timer' => $this->getTimer(),
            'percentage' => $this->getPercentage(),
            'tasks' => $this->getTasks()
        ];
    }
}
