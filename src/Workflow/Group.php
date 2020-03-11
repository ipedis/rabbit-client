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
     * @var int $order
     */
    private $order;

    /**
     * @var Task[] $tasks
     */
    protected $tasks = [];

    /**
     * @var callable $callback
     */
    protected $callback;

    protected function __construct(string $groupId, int $order, ?callable $callback, array $tasks = [])
    {
        $this->groupId  = $groupId;
        $this->order    = $order;
        $this->callback = $callback;
        $this->tasks    = $tasks;
    }

    /**
     * Factory method
     *
     * @param int $order
     * @param callable|null $callback
     * @return Group
     */
    public static function build(int $order, ?callable $callback): self
    {
        return new self(uuid_create(), $order, $callback, []);
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

    public function planifyOrder(OrderMessagePayload $order, ?callable $callback = null): self
    {
        return $this->planify(Task::build($order, $callback));
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
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @return bool
     */
    public function hasCallback(): bool
    {
        return !is_null($this->callback);
    }
}
