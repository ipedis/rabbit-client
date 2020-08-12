<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
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
     * @var $orders[]
     */
    protected $orders = [];

    /**
     * @var GroupConfig
     */
    protected $config;

    /**
     * Group constructor.
     * @param string $groupId
     * @param array $orders
     * @param array $callbacks
     * @param GroupConfig|null $config
     * @throws InvalidGroupArgumentException
     */
    protected function __construct(string $groupId, array $orders = [], array $callbacks = [], ?GroupConfig $config = null)
    {
        $this->groupId  = $groupId;
        $this->prepareOrders($orders);
        $this->callbacks = $this->assertCallbacks($callbacks);
        $this->config = $config;
    }

    /**
     * Factory constructor
     *
     * @param array $orders Array of tasks|workflow
     * @param array $callbacks Key/value array of callbacks
     * where key correspond to event name and value list of callbacks for event
     * @param GroupConfig|null $config
     * @return static
     * @throws InvalidGroupArgumentException
     */
    public static function build(array $orders = [], array $callbacks = [], ?GroupConfig $config = null): self
    {
        return new self(uuid_create(), $orders, $callbacks, $config);
    }

    /**
     * Planify a job in the group
     *
     * @param $order
     * @return Group
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
     * Planify a task in the group
     *
     * @param OrderMessagePayload $order
     * @param array $callbacks
     * @return Group
     * @throws InvalidGroupArgumentException
     */
    public function planifyOrder(OrderMessagePayload $order, array $callbacks = []): self
    {
        return $this->planify(Task::build($order, $callbacks));
    }

    /**
     * Planify a workflow in the group
     *
     * @param Workflow $workflow
     * @return Group
     * @throws InvalidGroupArgumentException
     */
    public function planifyWorkflow(Workflow $workflow): self
    {
        return $this->planify($workflow);
    }

    /**
     * Group has order matching orderId
     *
     * @param string $orderId
     * @return bool
     */
    public function has(string $orderId): bool
    {
        return (!empty($this->orders[$orderId]));
    }

    /**
     * @param string $orderId
     * @return Task
     */
    public function find(string $orderId): Task
    {
        return $this->orders[$orderId];
    }

    /**
     * @param ReplyMessagePayload $message
     * @return array
     * @throws InvalidStatusException
     */
    public function update(ReplyMessagePayload $message): array
    {
        $order = $this->find($message->getOrderId());
        $order->update($message);

        return [$this, $order];
    }

    /**
     * @param ReplyMessagePayload $message
     * @return array
     * @throws InvalidStatusException
     */
    public function retryTask(ReplyMessagePayload $message): array
    {
        $task = $this->find($message->getOrderId());
        $task->retry();

        return [$this, $task];
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
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @return bool
     */
    public function hasConfig(): bool
    {
        return !is_null($this->config);
    }

    /**
     * @return GroupConfig
     */
    public function getConfig(): GroupConfig
    {
        return $this->config;
    }

    /**
     * Check if retry is allowed for task
     *
     * @param Task $task
     * @return bool
     */
    public function canRetryTask(Task $task): bool
    {
        return
            $this->hasConfig() &&
            $this->getConfig()->hasToRetry() &&
            $task->getRetryCount() < $this->getConfig()->getMaxRetry()
        ;
    }

    /**
     * Check if further tasks can be dispatched for channel
     *
     * @param string $channelName
     * @param $maxWorkers
     * @return bool
     */
    public function canDispatchTask(string $channelName, $maxWorkers): bool
    {
        return
            $this->getProgressBag()->countDispatchedTasks($channelName) < $maxWorkers &&
            $this->getProgressBag()->countInProgressTasks($channelName) < $maxWorkers
        ;
    }

    /**
     * Get progress bag for tasks
     *
     * @return GroupProgressBag
     */
    public function getProgressBag(): GroupProgressBag
    {
        return new GroupProgressBag($this->getOrders(), $this->getGroupId());
    }

    /**
     * @return ProgressBag\Property\Status
     */
    public function getStatus()
    {
        return $this->getProgressBag()->getStatus();
    }

    /**
     * @return ProgressBag\Property\Percentage
     * @throws \Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException
     */
    public function getPercentage()
    {
        return $this->getProgressBag()->getPercentage();
    }

    /**
     * @return Timer
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
     * @param array $orders
     * @throws InvalidGroupArgumentException
     */
    private function prepareOrders(array $orders)
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
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::GROUP_ALLOW_TYPES;
    }
}
