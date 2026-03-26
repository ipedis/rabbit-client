<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\DTO\Order;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\DTO\Order\Order;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    #[Test]
    public function build_with_closure_handler(): void
    {
        $handler = function (): void {
        };
        $order = Order::build('order-123', MessageHandlerInterface::TYPE_PLANIFIED, $handler);

        $this->assertSame('order-123', $order->getOrderId());
        $this->assertSame(MessageHandlerInterface::TYPE_PLANIFIED, $order->getStatus());
        $this->assertSame($handler, $order->getHandler());
    }

    #[Test]
    public function build_with_message_handler_interface(): void
    {
        $handler = $this->createStub(MessageHandlerInterface::class);
        $order = Order::build('order-456', MessageHandlerInterface::TYPE_DISPATCHED, $handler);

        $this->assertSame('order-456', $order->getOrderId());
        $this->assertSame(MessageHandlerInterface::TYPE_DISPATCHED, $order->getStatus());
        $this->assertSame($handler, $order->getHandler());
    }

    #[Test]
    public function transition_to_changes_status(): void
    {
        $order = Order::build('order-123', MessageHandlerInterface::TYPE_PLANIFIED, function (): void {
        });

        $result = $order->transitionTo(MessageHandlerInterface::TYPE_DISPATCHED);

        $this->assertSame($order, $result);
        $this->assertSame(MessageHandlerInterface::TYPE_DISPATCHED, $order->getStatus());
    }

    #[Test]
    public function transition_to_multiple_statuses(): void
    {
        $order = Order::build('order-123', MessageHandlerInterface::TYPE_PLANIFIED, function (): void {
        });

        $order->transitionTo(MessageHandlerInterface::TYPE_DISPATCHED);
        $order->transitionTo(MessageHandlerInterface::TYPE_PROGRESS);
        $order->transitionTo(MessageHandlerInterface::TYPE_SUCCESS);

        $this->assertSame(MessageHandlerInterface::TYPE_SUCCESS, $order->getStatus());
    }
}
