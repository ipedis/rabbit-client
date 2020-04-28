<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


interface ProgressBagInterface
{
    const STATUS_PENDING  = 'pending';
    const STATUS_RUNNING  = 'running';
    const STATUS_FINISHED = 'finished';

    /**
     * @return bool
     */
    public function isPending(): bool;

    /**
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * @return bool
     */
    public function isCompleted(): bool;

    /**
     * @return bool
     */
    public function hasFailure(): bool;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return float
     */
    public function getExecutionTime(): float;

    /**
     * @return float
     */
    public function getPercentageProgression(): float;

    /**
     * @return array
     */
    public function getSummary(): array;
}
