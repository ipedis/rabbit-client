<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 7/2/20
 * Time: 10:36 AM
 */
namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;

use Ipedis\Rabbit\Exception\Progress\InvalidStatusException;

class Status implements \JsonSerializable, \Stringable
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUCCESS = 'success';

    public const AVAILABLE_STATUS = [
        self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED, self::STATUS_SUCCESS
    ];

    private readonly string $status;

    /**
     * Status constructor.
     * @throws InvalidStatusException
     */
    private function __construct(string $status)
    {
        $this->assertStatus($status);

        $this->status = $status;
    }

    /**
     * Validate the provided value for status.
     *
     * @throws InvalidStatusException
     */
    private function assertStatus(string $status): void
    {
        if (!$this->isStatusValid($status)) {
            throw new InvalidStatusException(sprintf('Status %s is not valid status value.', $status));
        }
    }

    /**
     * Checks if the given value is a valid status.
     */
    private function isStatusValid(string $status): bool
    {
        return in_array($status, self::AVAILABLE_STATUS);
    }

    /**
     * Build status object.
     *
     * @param string $status - current status value to set. Allowed values are (pending, running, failed, success).
     * @throws InvalidStatusException
     */
    public static function build(string $status): self
    {
        return new self($status);
    }

    public static function buildPending(): self
    {
        return new self(self::STATUS_PENDING);
    }

    /**
     * @return static
     */
    public static function buildRunning(): self
    {
        return new self(self::STATUS_RUNNING);
    }

    /**
     * @return static
     */
    public static function buildSuccess(): self
    {
        return new self(self::STATUS_SUCCESS);
    }

    /**
     * @return static
     */
    public static function buildFailed(): self
    {
        return new self(self::STATUS_FAILED);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function __toString(): string
    {
        return $this->getStatus();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function jsonSerialize(): array
    {
        return [
            'status' => $this->getStatus()
        ];
    }
}
