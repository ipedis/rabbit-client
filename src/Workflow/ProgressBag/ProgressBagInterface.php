<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\Task\TasksType;

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
     * @return TasksType
     */
    public function getTasks(): TasksType;
}
