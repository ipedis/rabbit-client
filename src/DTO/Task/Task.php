<?php

namespace Ipedis\Rabbit\DTO\Task;

/**
 * DTO class to abstract a task
 * A task should have a task id and a status
 *
 * Class Task
 * @package Ipedis\Rabbit\DTO\Task
 */
final class Task
{
    /**
     * @var string $taskId
     */
    private $taskId;

    /**
     * @var string $status
     */
    private $status;

    public function __construct(string $taskId, string $status)
    {
        $this->taskId = $taskId;
        $this->status = $status;
    }

    /**
     * Factory method
     *
     * @param string $taskId
     * @param string $status
     * @return Task
     */
    public static function build(string $taskId, string $status): self
    {
        return new self($taskId, $status);
    }

    /**
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
