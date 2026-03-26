<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class Percentage implements \JsonSerializable
{
    private readonly float $completed;

    private readonly float $success;

    private readonly float $failed;

    /**
     * Progress constructor.
     * @param float $completed - Overall progress in percentage
     * @param float $success - Overall success percentage in current progress
     * @param float $failed - Overall fail percentage in current progress
     * @throws InvalidProgressValueException
     */
    private function __construct(float $completed, float $success, float $failed)
    {
        $this->assertProgress($completed, $success, $failed);
        $this->completed = $completed;
        $this->success = $success;
        $this->failed = $failed;
    }

    /**
     * @throws InvalidProgressValueException
     */
    private function assertProgress(float $completed, float $success, float $failed): void
    {
        if (!$this->isCompletedValid($completed, $success, $failed) || !$this->isPercentageValid($success) || !$this->isPercentageValid($failed)) {
            throw new InvalidProgressValueException('Invalid progress value provided.');
        }
    }

    private function isCompletedValid(float $completed, float $success, float $failed): bool
    {
        return $this->isPercentageValid($completed) && ($completed === $success + $failed);
    }

    private function isPercentageValid(float $percentage): bool
    {
        return $percentage >= 0 && $percentage <= 100;
    }

    /**
     * @throws InvalidProgressValueException
     */
    public static function build(float $completed, float $success, float $failed): self
    {
        return new self($completed, $success, $failed);
    }

    /**
     * Initialize progress object.
     * @throws InvalidProgressValueException
     */
    public static function init(): self
    {
        return new self(0, 0, 0);
    }

    public static function calculate(int $completed, int $total): float
    {
        return (100 * $completed) / $total;
    }

    /**
     * @return array{completed: float, success: float, failed: float}
     */
    public function jsonSerialize(): array
    {
        return [
            'completed' => $this->getCompleted(),
            'success' => $this->getSuccess(),
            'failed' => $this->getFailed()
        ];
    }

    public function getCompleted(): float
    {
        return $this->completed;
    }

    public function getSuccess(): float
    {
        return $this->success;
    }

    public function getFailed(): float
    {
        return $this->failed;
    }
}
