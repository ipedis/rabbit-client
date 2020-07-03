<?php
/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 7/2/20
 * Time: 10:36 AM
 */

namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;


use Ipedis\Rabbit\Exception\Progress\InvalidStatusException;

class Status implements \JsonSerializable
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_FAILED  = 'failed';
    const STATUS_SUCCESS = 'success';

    public const AVAILABLE_STATUS = [
        self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED, self::STATUS_SUCCESS
    ];

    /**
     * @var string
     */
    private string $status;

    /**
     * Status constructor.
     * @param string $status
     * @throws InvalidStatusException
     */
    private function __construct(string $status)
    {
        $this->assertStatus($status);

        $this->status = $status;
    }

    /**
     * Build status object.
     *
     * @param string $status - current status value to set. Allowed values are (pending, running, failed, success).
     * @return Status
     * @throws InvalidStatusException
     */
    public static function build(string $status): self
    {
        return new self($status);
    }

    /**
     * @return Status
     */
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

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getStatus();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->getStatus()
        ];
    }

    /**
     * Validate the provided value for status.
     *
     * @param string $status
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
     *
     * @param string $status
     * @return bool
     */
    private function isStatusValid(string $status): bool
    {
        return in_array($status, self::AVAILABLE_STATUS);
    }
}