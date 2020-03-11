<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;

class Group
{
    /**
     * @var string $groupId
     */
    private $groupId;

    /**
     * @var Task[] $tasks
     */
    protected $tasks = [];

    /**
     * @var array $callbacks
     */
    protected $callbacks;

    protected function __construct(string $groupId, ?callable $callback = null, array $tasks = [])
    {
        $this->groupId  = $groupId;
        $this->tasks    = $tasks;

        if (is_callable($callback)) {
            $this->callbacks[] = $callback;
        }
    }

    /**
     * Factory method
     *
     * @param callable|null $callback
     * @return Group
     */
    public static function build(?callable $callback): self
    {
        return new self(uuid_create(), $callback, []);
    }

    /**
     * Planify a task in the group
     *
     * @param Task $task
     * @return Group
     */
    public function planify(Task $task): self
    {
        $this->tasks[] = $task;

        return $this;
    }

    /**
     * Planify a task in the group
     *
     * @param OrderMessagePayload $order
     * @param callable|null $callback
     * @return Group
     */
    public function planifyOrder(OrderMessagePayload $order, ?callable $callback = null): self
    {
        return $this->planify(Task::build($order, $callback));
    }

    /**
     * Add global callback to group
     *
     * @param callable $callback
     * @return Group
     */
    public function bind(callable $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Get all scheduled tasks
     *
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @return array
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @return bool
     */
    public function hasCallbacks(): bool
    {
        return count($this->callbacks) > 0;
    }
}
