<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\DTO\Order;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;

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
        private readonly \Closure|MessageHandlerInterface $handler
    ) {
    }

    /**
     * Factory method
     */
    public static function build(string $orderId, string $status, \Closure|MessageHandlerInterface $handler): self
    {
        return new self($orderId, $status, $handler);
    }

    public function transitionTo(string $newStatus): self
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

    public function getHandler(): \Closure|MessageHandlerInterface
    {
        return $this->handler;
    }
}
