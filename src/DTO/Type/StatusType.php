<?php


namespace Ipedis\Rabbit\DTO\Type;


class StatusType implements \JsonSerializable
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_FAILED  = 'failed';
    const STATUS_SUCCESS = 'success';

    public const AVAILABLES_STATUS = [
        self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_FAILED, self::STATUS_SUCCESS
    ];

    private $status;

    private function __construct(string $status)
    {
        $this->assertStatus($status);
        $this->status = $status;
    }

    public static function buildPending()
    {
        return new self(self::STATUS_PENDING);
    }

    public static function buildRunning()
    {
        return new self(self::STATUS_RUNNING);
    }

    public static function buildSuccess()
    {
        return new self(self::STATUS_SUCCESS);
    }

    public static function buildFailed()
    {
        return new self(self::STATUS_FAILED);
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning()
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isSuccess()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    public function jsonSerialize()
    {
        return [
            'status' => $this->getStatus()
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * @throws \Exception
     */
    private function assertStatus(string $status): void
    {
        if (!in_array($status, self::AVAILABLES_STATUS)) {
            throw new \Exception("{$status} is not a valid status");
        }
    }
}
