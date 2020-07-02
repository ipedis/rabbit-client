<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Contract;


use Ipedis\Rabbit\DTO\Order\Tasks;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;

interface ProgressBagInterface
{
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
     * @return Status
     */
    public function getStatus(): Status;

    /**
     * @return float
     */
    public function getExecutionTime(): float;

    /**
     * @return Percentage
     */
    public function getPercentage(): Percentage;

    /**
     * @return Tasks
     */
    public function getOrders(): Tasks;
}
