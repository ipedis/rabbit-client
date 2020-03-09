<?php

namespace Ipedis\Rabbit\DTO\Order;

/**
 * DTO class to abstract a task
 * A task should have a task id and a status
 *
 * Class Task
 * @package Ipedis\Rabbit\DTO\Task
 */
final class Order
{
    /**
     * @var string $orderId
     */
    private $orderId;

    /**
     * @var string $status
     */
    private $status;

    /**
     * @var $handler
     */
    private $handler;

    public function __construct(string $orderId, string $status, $handler)
    {
        $this->orderId = $orderId;
        $this->status = $status;
        $this->handler = $handler;
    }

    /**
     * Factory method
     *
     * @param string $orderId
     * @param string $status
     * @param callable $handler
     * @return Order
     */
    public static function build(string $orderId, string $status, $handler): self
    {
        return new self($orderId, $status, $handler);
    }

    /**
     * @param $newStatus
     * @return Order
     */
    public function transitionTo($newStatus): self
    {
        $this->status = $newStatus;

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHandler()
    {
        return $this->handler;
    }
}
