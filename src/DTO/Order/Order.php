<?php

declare(strict_types=1);

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
    public function __construct(
        private readonly string $orderId,
        private string $status,
        /**
         * @var $handler
         */
        private $handler
    )
    {
    }

    /**
     * Factory method
     *
     * @param callable $handler
     */
    public static function build(string $orderId, string $status, $handler): self
    {
        return new self($orderId, $status, $handler);
    }

    /**
     * @param $newStatus
     */
    public function transitionTo($newStatus): self
    {
        $this->status = $newStatus;

        return $this;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHandler()
    {
        return $this->handler;
    }
}
