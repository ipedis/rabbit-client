<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\GroupProgressBag;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

class Group extends Bindable
{
    /**
     * Group orders
     *
     * @var $orders []
     */
    protected $orders = [];


    /**
     * Group constructor.
     * @throws InvalidGroupArgumentException
     */
    protected function __construct(/**
     * Group Identifier
     */
    private readonly string $groupId, array $orders = [], array $callbacks = [], protected ?\Ipedis\Rabbit\Workflow\Config\GroupConfig $config = null)
    {
        $this->prepareOrders($orders);
        $this->callbacks = $this->assertCallbacks($callbacks);
    }

    /**
     * @throws InvalidGroupArgumentException
     */
    private function prepareOrders(array $orders): void
    {
        foreach ($orders as $order) {
            if (!$order instanceof Task && !$order instanceof Workflow) {
                throw new InvalidGroupArgumentException(sprintf('list of tasks must have "%s" type or "%s" type', Task::class, Workflow::class));
            }

            if ($order instanceof Workflow) {
                $this->orders[$order->getWorkflowId()] = $order;
            } else {
                $this->orders[$order->getOrderMessage()->getOrderId()] = $order;
            }
        }
    }

    /**
     * Factory constructor
     *
     * @param array $orders Array of tasks|workflow
     * @param array $callbacks Key/value array of callbacks
     * where key correspond to event name and value list of callbacks for event
     * @return static
     * @throws InvalidGroupArgumentException
     */
    public static function build(array $orders = [], array $callbacks = [], ?GroupConfig $config = null): self
    {
        return new self(uuid_create(), $orders, $callbacks, $config);
    }

    /**
     * Planify a task in the group
     *
     * @throws InvalidGroupArgumentException
     */
    public function planifyOrder(OrderMessagePayload $order, array $callbacks = []): self
    {
        return $this->planify(Task::build($order, $callbacks));
    }

    /**
     * Planify a job in the group
     *
     * @param $order
     * @throws InvalidGroupArgumentException
     */
    public function planify($order): self
    {
        if (!$order instanceof Task && !$order instanceof Workflow) {
            throw new InvalidGroupArgumentException(sprintf('list of tasks must have "%s" type or "%s" type', Task::class, Workflow::class));
        }

        if ($order instanceof Workflow) {
            $this->orders[$order->getWorkflowId()] = $order;
        } else {
            $this->orders[$order->getOrderMessage()->getOrderId()] = $order;
        }

        return $this;
    }

    /**
     * Planify a workflow in the group
     *
     * @throws InvalidGroupArgumentException
     */
    public function planifyWorkflow(Workflow $workflow): self
    {
        return $this->planify($workflow);
    }

    /**
     * Group has order matching orderId
     */
    public function has(string $orderId): bool
    {
        return (!empty($this->orders[$orderId]));
    }

    /**
     * @throws InvalidStatusException
     */
    public function update(ReplyMessagePayload $message): array
    {
        $order = $this->find($message->getOrderId());
        $order->update($message);

        return [$this, $order];
    }

    public function find(string $orderId): Task
    {
        return $this->orders[$orderId];
    }

    /**
     * @throws InvalidStatusException
     */
    public function retryTask(ReplyMessagePayload $message): array
    {
        $task = $this->find($message->getOrderId());
        $task->retry();

        return [$this, $task];
    }

    /**
     * Check if retry is allowed for task
     */
    public function canRetryTask(Task $task): bool
    {
        return
            $this->hasConfig() &&
            $this->getConfig()->hasToRetry() &&
            $task->getRetryCount() < $this->getConfig()->getMaxRetry();
    }

    public function hasConfig(): bool
    {
        return !is_null($this->config);
    }

    public function getConfig(): GroupConfig
    {
        return $this->config;
    }

    /**
     * Check if further tasks can be dispatched for channel
     *
     * @param $maxWorkers
     */
    public function canDispatchTask(string $channelName, $maxWorkers): bool
    {
        return
            $this->getProgressBag()->countDispatchedTasks($channelName) < $maxWorkers &&
            $this->getProgressBag()->countInProgressTasks() < $maxWorkers;
    }

    /**
     * Get progress bag for tasks
     */
    public function getProgressBag(): GroupProgressBag
    {
        return new GroupProgressBag($this->getOrders(), $this->getGroupId());
    }

    /**
     * Get all scheduled tasks
     *
     * @return Task[]
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * @return Task[]
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->getOrders(), fn (Task $task): bool => $task->getStatus() === MessageHandlerInterface::TYPE_ERROR);
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return array_map(fn (Task $task): \Ipedis\Rabbit\Exception\Helper\Error => Serializer::fromMessage($task->getLastReplyMessage()), $this->getFailedOrders());
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getStatus(): \Ipedis\Rabbit\Workflow\ProgressBag\Property\Status
    {
        return $this->getProgressBag()->getStatus();
    }

    /**
     * @throws \Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException
     */
    public function getPercentage(): \Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage
    {
        return $this->getProgressBag()->getPercentage();
    }

    /**
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public function getTimer(): Timer
    {
        return Timer::build(
            $this->getProgressBag()->getExecutionTime(),
            $this->getProgressBag()->getStartedAt(),
            $this->getProgressBag()->getFinishedAt()
        );
    }

    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::GROUP_ALLOW_TYPES;
    }
}
