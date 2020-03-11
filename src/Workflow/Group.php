<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
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


    protected function __construct(string $groupId, array $callbacks = [], array $tasks = [])
    {
        $this->groupId  = $groupId;
        $this->prepareTasks($tasks);
        $this->assertCallbacks($callbacks);
        $this->callbacks = $callbacks;
    }

    /**
     * Factory method
     *
     * @param array $callbacks
     * @param array $tasks
     * @return Group
     */
    public static function build(array $callbacks = [], array $tasks = []): self
    {
        return new self(uuid_create(), $callbacks, $tasks);
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
        $this->tasks[$task->getMessage()->getOrderId()] = $task;

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

    public function has(string $orderId): bool
    {
        return (!empty($this->tasks[$orderId]));
    }

    public function find(string $orderId): Task
    {
        return $this->tasks[$orderId];
    }

    public function update(ReplyMessagePayload $message): self
    {
        $this->find($message->getOrderId())->update($message);

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

    private function prepareTasks($tasks)
    {
        foreach ($tasks as $task) {
            if (!($task instanceof Task)) {
                throw new InvalidGroupArgumentException(sprintf('list of tasks must have "%s" type', Task::class));
            }
            $this->tasks[$task->getMessage()->getOrderId()] = $task;
        }
    }

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

    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::GROUP_ALLOW_TYPES;
    }
}
