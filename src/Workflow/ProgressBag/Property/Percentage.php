<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class Percentage implements \JsonSerializable
{
    /**
     * @var float
     */
    private float $completed;

    /**
     * @var float
     */
    private float $success;
    private float $failed;

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
     * @param float $completed
     * @param float $success
     * @param float $failed
     * @return Percentage
     * @throws InvalidProgressValueException
     */
    public static function build(float $completed, float $success, float $failed): self
    {
        return new self($completed, $success, $failed);
    }

    /**
     * Initialize progress object.
     * @return Percentage
     * @throws InvalidProgressValueException
     */
    public static function init(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * @param int $completed
     * @param int $total
     * @return float
     */
    public static function calculate(int $completed, int $total): float
    {
        return (100 * $completed)/$total;
    }

    /**
     * @return float
     */
    public function getCompleted(): float
    {
        return $this->completed;
    }

    /**
     * @return float
     */
    public function getSuccess(): float
    {
        return $this->success;
    }

    /**
     * @return float
     */
    public function getFailed(): float
    {
        return $this->failed;
    }

    public function jsonSerialize()
    {
        return [
            'completed' => $this->getCompleted(),
            'success'   => $this->getSuccess(),
            'failed'    => $this->getFailed()
        ];
    }

    /**
     * @param float $completed
     * @param float $success
     * @param float $failed
     * @throws InvalidProgressValueException
     */
    private function assertProgress(float $completed, float $success, float $failed)
    {
        if (!$this->isCompletedValid($completed, $success, $failed) || !$this->isPercentageValid($success) || !$this->isPercentageValid($failed)) {
            throw new InvalidProgressValueException('Invalid progress value provided.');
        }
    }

    /**
     * @param float $completed
     * @param float $success
     * @param float $failed
     * @return bool
     */
    private function isCompletedValid(float $completed, float $success, float $failed): bool
    {
        return $this->isPercentageValid($completed) && ($completed === $success + $failed);
    }

    /**
     * @param float $percentage
     * @return bool
     */
    private function isPercentageValid(float $percentage): bool
    {
        return $percentage >=0 && $percentage <= 100;
    }
}
