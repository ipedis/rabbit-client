<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class TaskProgress implements \JsonSerializable
{
    /**
     * @var string
     */
    private string $uuid;
    /**
     * @var string
     */
    private string $type;
    /**
     * @var Status
     */
    private Status $status;
    /**
     * @var Timer
     */
    private Timer $timer;

    /**
     * Task constructor.
     * @param string $uuid
     * @param string $type
     * @param Status $status
     * @param Timer $timer
     * @throws InvalidUuidException
     */
    private function __construct(string $uuid, string $type, Status $status, Timer $timer)
    {
        $this->assertUuid($uuid);
        $this->uuid = $uuid;
        $this->type = $type;
        $this->status = $status;
        $this->timer = $timer;
    }

    /**
     * @param string $uuid
     * @param string $type
     * @param Status $status
     * @param Timer $timer
     * @return TaskProgress
     * @throws InvalidUuidException
     */
    public static function build(string $uuid, string $type, Status $status, Timer $timer): self
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
    private function assertUuid(string $uuid)
    {
        (new UuidValidator())->validate($uuid);
    }
}
