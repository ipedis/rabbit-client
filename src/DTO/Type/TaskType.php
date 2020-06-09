<?php


namespace Ipedis\Rabbit\DTO\Type;


use Ipedis\Rabbit\Exception\InvalidUuidException;

class TaskType implements \JsonSerializable
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $type;

    /**
     * @var StatusType
     */
    private $status;

    /**
     * @var TimerType
     */
    private $timer;

    private function __construct(string $uuid, string $type, StatusType $status, TimerType $timer)
    {
        $this->assertUuid($uuid);
        $this->uuid = $uuid;
        $this->type = $type;
        $this->status = $status;
        $this->timer = $timer;
    }

    public static function build(string $uuid, string $type, StatusType $status, TimerType $timer)
    {
        return new self($uuid, $type, $status, $timer);
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'uuid' => $this->getUuid(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'timer' => $this->getTimer()
        ];
    }

    /**
     * @param string $uuid
     * @throws InvalidUuidException
     */
    private function assertUuid(string $uuid): void
    {
        if (!uuid_is_valid($uuid)) {
            throw new InvalidUuidException("{$uuid} is not a valid uuid");
        }
    }
}
