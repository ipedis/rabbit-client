<?php

namespace Ipedis\Rabbit\Workflow;


class WorkflowGroup
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
     * @var array $tasks
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
     * @return WorkflowGroup
     */
    public static function build(int $order, ?callable $callback): self
    {
        return new self(uuid_create(), $order, $callback, []);
    }

    /**
     * Planify a task in the group
     *
     * @param array $data
     * @param callable $callback
     * @return WorkflowGroup
     */
    public function planify(array $data, callable $callback): self
    {
        $this->tasks[] = [
            'data' => $data,
            'callback' => $callback
        ];

        return $this;
    }

    /**
     * Get all scheduled tasks
     *
     * @return array
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
