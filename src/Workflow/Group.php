<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\GroupProgressBag;

class Group extends Bindable
{
    /**
     * Group Identifier
     *
     * @var string $groupId
     */
    private $groupId;

    /**
     * Group orders
     *
     * @var Task[] $tasks
     */
    protected $tasks = [];

    /**
     * @var GroupConfig
     */
    protected $config;

    /**
     * Group constructor.
     * @param string $groupId
     * @param array $tasks
     * @param array $callbacks
     * @param GroupConfig|null $config
     * @throws InvalidGroupArgumentException
     */
    protected function __construct(string $groupId, array $tasks = [], array $callbacks = [], ?GroupConfig $config = null)
    {
        $this->groupId  = $groupId;
        $this->prepareTasks($tasks);
        $this->callbacks = $this->assertCallbacks($callbacks);
        $this->config = $config;
    }

    /**
     * Factory constructor
     *
     * @param array $tasks Array of tasks
     * @param array $callbacks Key/value array of callbacks
     * where key correspond to event name and value list of callbacks for event
     * @param GroupConfig|null $config
     * @return static
     * @throws InvalidGroupArgumentException
     */
    public static function build(array $tasks = [], array $callbacks = [], ?GroupConfig $config = null): self
    {
        return new self(uuid_create(), $tasks, $callbacks, $config);
    }

    /**
     * Planify a task in the group
     *
     * @param Task $task
     * @return Group
     */
    public function planify(Task $task): self
    {
        // TODO : we should probably check if index does not exist, otherwise we will erease another task.
        $this->tasks[$task->getOrderMessage()->getOrderId()] = $task;

        return $this;
    }

    /**
     * Planify a task in the group
     *
     * @param OrderMessagePayload $order
     * @param array $callbacks
     * @return Group
     */
    public function planifyOrder(OrderMessagePayload $order, array $callbacks = []): self
    {
        return $this->planify(Task::build($order, $callbacks));
    }

    /**
     * Group has order matching orderId
     *
     * @param string $orderId
     * @return bool
     */
    public function has(string $orderId): bool
    {
        return (!empty($this->tasks[$orderId]));
    }

    /**
     * @param string $orderId
     * @return Task
     */
    public function find(string $orderId): Task
    {
        return $this->tasks[$orderId];
    }

    /**
     * @param ReplyMessagePayload $message
     * @return array
     * @throws InvalidStatusException
     */
    public function update(ReplyMessagePayload $message): array
    {
        $task = $this->find($message->getOrderId());
        $task->update($message);

        return [$this, $task];
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
     * @return GroupConfig
     */
    public function getConfig(): GroupConfig
    {
        return $this->config;
    }

    /**
     * Get progress bag for tasks
     *
     * @return GroupProgressBag
     */
    public function getProgressBag(): GroupProgressBag
    {
        return new GroupProgressBag($this->getTasks());
    }

    /**
     * @param array $tasks
     * @throws InvalidGroupArgumentException
     */
    private function prepareTasks(array $tasks)
    {
        foreach ($tasks as $task) {
            if (!($task instanceof Task)) {
                throw new InvalidGroupArgumentException(sprintf('list of tasks must have "%s" type', Task::class));
            }
            $this->tasks[$task->getOrderMessage()->getOrderId()] = $task;
        }
    }

    /**
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::GROUP_ALLOW_TYPES;
    }
}
