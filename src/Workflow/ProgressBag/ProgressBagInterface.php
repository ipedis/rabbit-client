<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


use Ipedis\Rabbit\DTO\Order\Tasks;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;

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
     * @return StatusType
     */
    public function getStatus(): StatusType;

    /**
     * @return float
     */
    public function getExecutionTime(): float;

    /**
     * @return ProgressType
     */
    public function getPercentage(): ProgressType;

    /**
     * @return Tasks
     */
    public function getOrders(): Tasks;
}
