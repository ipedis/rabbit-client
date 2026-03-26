<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\TaskProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use Ipedis\Rabbit\Workflow\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    private const CHANNEL = 'v1.service.aggregate.action';

    private const REPLY_QUEUE = 'reply.queue';

    private function makeOrderMessage(): OrderMessagePayload
    {
        return OrderMessagePayload::build(self::CHANNEL, ['key' => 'value'], [
            'replyQueue' => self::REPLY_QUEUE,
        ]);
    }

    private function makeReplyMessage(string $status, ?string $orderId = null): ReplyMessagePayload
    {
        return ReplyMessagePayload::fromArray([
            'header' => [
                'channel' => self::CHANNEL,
                'correlation_id' => $orderId ?? $this->makeOrderMessage()->getOrderId(),
                'status' => $status,
            ],
            'data' => [],
        ]);
    }

    #[Test]
    public function build_creates_task_with_planified_status(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->assertSame(MessageHandlerInterface::TYPE_PLANIFIED, $task->getStatus());
        $this->assertTrue($task->isPlanified());
        $this->assertFalse($task->isDispatched());
        $this->assertFalse($task->isInProgress());
        $this->assertFalse($task->isCompleted());
        $this->assertFalse($task->isSuccess());
        $this->assertFalse($task->isOnFailure());
    }

    #[Test]
    public function get_task_id_returns_order_id(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->assertSame($order->getOrderId(), $task->getTaskId());
    }

    #[Test]
    public function get_order_message_returns_original_message(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->assertSame($order, $task->getOrderMessage());
    }

    #[Test]
    public function set_task_as_dispatched_transitions_from_planified(): void
    {
        $task = Task::build($this->makeOrderMessage());
        $task->setTaskAsDispatched();

        $this->assertTrue($task->isDispatched());
        $this->assertFalse($task->isPlanified());
        $this->assertSame(MessageHandlerInterface::TYPE_DISPATCHED, $task->getStatus());
    }

    #[Test]
    public function set_task_as_dispatched_does_nothing_if_not_planified(): void
    {
        $task = Task::build($this->makeOrderMessage());
        $task->setTaskAsDispatched();
        $this->assertTrue($task->isDispatched());

        // Try to dispatch again — should stay dispatched, not throw
        $task->setTaskAsDispatched();
        $this->assertTrue($task->isDispatched());
    }

    #[Test]
    public function update_transitions_to_progress(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);
        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId());

        $task->update($reply);

        $this->assertTrue($task->isInProgress());
        $this->assertFalse($task->isCompleted());
        $this->assertInstanceOf(\DateTime::class, $task->getStartTime());
        $this->assertNotInstanceOf(\DateTime::class, $task->getFinishedTime());
    }

    #[Test]
    public function update_transitions_to_success(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $order->getOrderId()));

        $this->assertTrue($task->isSuccess());
        $this->assertTrue($task->isCompleted());
        $this->assertFalse($task->isOnFailure());
        $this->assertInstanceOf(\DateTime::class, $task->getFinishedTime());
    }

    #[Test]
    public function update_transitions_to_error(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $order->getOrderId()));

        $this->assertTrue($task->isOnFailure());
        $this->assertTrue($task->isCompleted());
        $this->assertFalse($task->isSuccess());
    }

    #[Test]
    public function update_with_invalid_status_throws_exception(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->expectException(InvalidStatusException::class);
        $task->update($this->makeReplyMessage('invalid_status', $order->getOrderId()));
    }

    #[Test]
    public function reply_messages_are_tracked(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->assertFalse($task->hasReplyMessage());
        $this->assertSame([], $task->getReplyMessages());
        $this->assertNotInstanceOf(ReplyMessagePayload::class, $task->getLastReplyMessage());

        $reply1 = $this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId());
        $task->update($reply1);

        $this->assertTrue($task->hasReplyMessage());
        $this->assertCount(1, $task->getReplyMessages());
        $this->assertSame($reply1, $task->getLastReplyMessage());

        $reply2 = $this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $order->getOrderId());
        $task->update($reply2);

        $this->assertCount(2, $task->getReplyMessages());
        $this->assertSame($reply2, $task->getLastReplyMessage());
    }

    #[Test]
    public function retry_resets_to_planified_and_increments_count(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $this->assertSame(0, $task->getRetryCount());

        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $order->getOrderId()));
        $task->retry();

        $this->assertTrue($task->isPlanified());
        $this->assertSame(1, $task->getRetryCount());

        $task->retry();
        $this->assertSame(2, $task->getRetryCount());
    }

    #[Test]
    public function execution_time_is_zero_when_not_started(): void
    {
        $task = Task::build($this->makeOrderMessage());

        $this->assertEqualsWithDelta(0.0, $task->getExecutionTime(), PHP_FLOAT_EPSILON);
        $this->assertNotInstanceOf(\DateTime::class, $task->getStartTime());
        $this->assertNotInstanceOf(\DateTime::class, $task->getFinishedTime());
    }

    #[Test]
    public function execution_time_is_zero_when_started_but_not_finished(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));

        $this->assertEqualsWithDelta(0.0, $task->getExecutionTime(), PHP_FLOAT_EPSILON);
        $this->assertInstanceOf(\DateTime::class, $task->getStartTime());
        $this->assertNotInstanceOf(\DateTime::class, $task->getFinishedTime());
    }

    #[Test]
    public function execution_time_is_calculated_when_completed(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $order->getOrderId()));

        $this->assertGreaterThanOrEqual(0.0, $task->getExecutionTime());
        $this->assertInstanceOf(\DateTime::class, $task->getStartTime());
        $this->assertInstanceOf(\DateTime::class, $task->getFinishedTime());
    }

    #[Test]
    public function get_type_returns_channel_type(): void
    {
        $task = Task::build($this->makeOrderMessage());

        $this->assertSame('service.aggregate.action', $task->getType());
    }

    #[Test]
    public function get_progress_status_maps_correctly(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        // Planified -> pending
        $this->assertTrue($task->getProgressStatus()->isPending());

        // Dispatched -> running
        $task->setTaskAsDispatched();
        $this->assertTrue($task->getProgressStatus()->isRunning());

        // Progress -> running
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));
        $this->assertTrue($task->getProgressStatus()->isRunning());

        // Success -> success
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $order->getOrderId()));
        $this->assertTrue($task->getProgressStatus()->isSuccess());
    }

    #[Test]
    public function get_progress_status_for_error(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $order->getOrderId()));

        $this->assertTrue($task->getProgressStatus()->isFailed());
    }

    #[Test]
    public function get_progress_status_for_starting(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);
        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_STARTING, $order->getOrderId()));

        $this->assertTrue($task->getProgressStatus()->isPending());
    }

    #[Test]
    public function get_timer_returns_timer_instance(): void
    {
        $task = Task::build($this->makeOrderMessage());
        $timer = $task->getTimer();

        $this->assertInstanceOf(Timer::class, $timer);
        $this->assertEqualsWithDelta(0.0, $timer->getSpent(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function get_task_progress_returns_task_progress(): void
    {
        $task = Task::build($this->makeOrderMessage());
        $progress = $task->getTaskProgress();

        $this->assertInstanceOf(TaskProgress::class, $progress);
    }

    #[Test]
    public function build_with_callbacks(): void
    {
        $called = false;
        $task = Task::build($this->makeOrderMessage(), [
            BindableEventInterface::TASK_ON_START => function () use (&$called): void {
                $called = true;
            },
        ]);

        $task->call(BindableEventInterface::TASK_ON_START);
        $this->assertTrue($called);
    }

    #[Test]
    public function bind_invalid_event_type_throws_exception(): void
    {
        $task = Task::build($this->makeOrderMessage());

        $this->expectException(\LogicException::class);
        $task->bind('invalid.event', function (): void {
        });
    }

    #[Test]
    public function start_time_only_set_on_first_progress(): void
    {
        $order = $this->makeOrderMessage();
        $task = Task::build($order);

        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));

        $firstStart = $task->getStartTime();

        $task->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_PROGRESS, $order->getOrderId()));
        $secondStart = $task->getStartTime();

        $this->assertSame($firstStart, $secondStart);
    }
}
