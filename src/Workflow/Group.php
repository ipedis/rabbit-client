<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;

class Group extends Bindable
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
     * @param array $tasks
     * @param array $callbacks
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
     * @return bool
     */
    public function isFinish(): bool
    {
        $isFinish = true;

        foreach ($this->tasks as $task) {
            if (!$task->isFinished()) {
               $isFinish = false;
               break;
            }
        }

        return $isFinish;
    }

    /**
     * @return GroupConfig
     */
    public function getConfig(): GroupConfig
    {
        return $this->config;
    }

    /**
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isFinished();
        });
    }

    /**
     * Get collection of in progress orders
     *
     * @return array
     */
    public function getInProgressOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isInProgress();
        });
    }

    /**
     * Get collection of successfully completed orders
     *
     * @return array
     */
    public function getSuccessfulOrders(): array
    {
        return array_filter($this->tasks, function(Task $task) {
            return $task->isSuccess();
        });
    }

    /**
     * Get collection of completed orders which have failed
     *
     * @return array
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->tasks, function (Task $task) {
            return $task->isOnFailure();
        });
    }

    public function hasFailure(): bool
    {
        return count($this->getFailedOrders()) > 0;
    }

    /**
     * Get percentage progression of orders
     *
     * @return float
     */
    public function getPercentageProgression(): float
    {
        $percentage = (count($this->getCompletedOrders()) / count($this->getTasks())) * 100;

        return round($percentage, 2);
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
