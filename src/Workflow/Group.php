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
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\GroupProgressBag;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;

/** @phpstan-consistent-constructor */
class Group extends Bindable
{
    /**
     * Group orders
     *
     * @var array<string, Task|Workflow>
     */
    protected array $orders = [];


    /**
     * Group constructor.
     *
     * @param array<int, Task|Workflow> $orders
     * @param array<string, callable|list<callable>> $callbacks
     * @throws InvalidGroupArgumentException
     */
    protected function __construct(/**
     * Group Identifier
     */
        private readonly string $groupId,
        array $orders = [],
        array $callbacks = [],
        protected ?GroupConfig $config = null
    ) {
        $this->prepareOrders($orders);
        $this->callbacks = $this->assertCallbacks($callbacks);
    }

    /**
     * @param array<int, Task|Workflow> $orders
     */
    private function prepareOrders(array $orders): void
    {
        foreach ($orders as $order) {
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
     * @param array<int, Task|Workflow> $orders Array of tasks|workflow
     * @param array<string, callable|list<callable>> $callbacks Key/value array of callbacks
     *                                                          where key correspond to event name and value list of callbacks for event
     * @throws InvalidGroupArgumentException
     */
    public static function build(array $orders = [], array $callbacks = [], ?GroupConfig $config = null): static
    {
        /** @var string $uuid */
        $uuid = uuid_create();

        return new static($uuid, $orders, $callbacks, $config);
    }

    /**
     * Planify a task in the group
     *
     * @param array<string, callable|list<callable>> $callbacks
     * @throws InvalidGroupArgumentException
     */
    public function planifyOrder(OrderMessagePayload $order, array $callbacks = []): static
    {
        return $this->planify(Task::build($order, $callbacks));
    }

    /**
     * Planify a job in the group
     *
     * @throws InvalidGroupArgumentException
     */
    public function planify(Task|Workflow $order): static
    {
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
    public function planifyWorkflow(Workflow $workflow): static
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
     * @return array{0: Group, 1: Task}
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
        $order = $this->orders[$orderId];
        assert($order instanceof Task);

        return $order;
    }

    /**
     * @return array{0: Group, 1: Task}
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
        assert($this->config instanceof GroupConfig);

        return $this->config;
    }

    /**
     * Check if further tasks can be dispatched for channel
     */
    public function canDispatchTask(string $channelName, int $maxWorkers): bool
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
     * @return array<string, Task|Workflow>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * @return array<string, Task>
     */
    public function getFailedOrders(): array
    {
        return array_filter($this->getTaskOrders(), fn (Task $task): bool => $task->getStatus() === MessageHandlerInterface::TYPE_ERROR);
    }

    /**
     * @return array<string, Task>
     */
    private function getTaskOrders(): array
    {
        /** @var array<string, Task> $tasks */
        $tasks = array_filter($this->orders, fn (Task|Workflow $order): bool => $order instanceof Task);

        return $tasks;
    }

    /**
     * @return array<string, Error>
     */
    public function getErrors(): array
    {
        return array_map(function (Task $task): Error {
            $replyMessage = $task->getLastReplyMessage();
            assert($replyMessage instanceof ReplyMessagePayload);

            return Serializer::fromMessage($replyMessage);
        }, $this->getFailedOrders());
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getStatus(): Status
    {
        return $this->getProgressBag()->getStatus();
    }

    /**
     * @throws InvalidProgressValueException
     */
    public function getPercentage(): Percentage
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

    /**
     * @return list<string>
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::GROUP_ALLOW_TYPES;
    }
}
