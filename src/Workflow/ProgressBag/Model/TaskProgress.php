<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Validator\UuidValidator;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class TaskProgress implements \JsonSerializable
{
    private readonly string $uuid;

    /**
     * Task constructor.
     * @throws InvalidUuidException
     */
    private function __construct(string $uuid, private readonly string $type, private readonly Status $status, private readonly Timer $timer)
    {
        $this->assertUuid($uuid);
        $this->uuid = $uuid;
    }

    /**
     * @throws InvalidUuidException
     */
    private function assertUuid(string $uuid): void
    {
        (new UuidValidator())->validate($uuid);
    }

    /**
     * @throws InvalidUuidException
     */
    public static function build(string $uuid, string $type, Status $status, Timer $timer): self
    {
        return new self($uuid, $type, $status, $timer);
    }

    /**
     * @return array{uuid: string, type: string, status: Status, timer: Timer}
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'timer' => $this->getTimer()
        ];
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getTimer(): Timer
    {
        return $this->timer;
    }
}
