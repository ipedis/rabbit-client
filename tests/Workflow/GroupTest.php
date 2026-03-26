<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\GroupProgressBag;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GroupTest extends TestCase
{
    private const CHANNEL = 'v1.service.aggregate.action';

    private const REPLY_QUEUE = 'reply.queue';

    private function makeOrderMessage(): OrderMessagePayload
    {
        return OrderMessagePayload::build(self::CHANNEL, ['key' => 'value'], [
            'replyQueue' => self::REPLY_QUEUE,
        ]);
    }

    private function makeTask(): Task
    {
        return Task::build($this->makeOrderMessage());
    }

    private function makeReplyMessage(string $status, string $orderId): ReplyMessagePayload
    {
        return ReplyMessagePayload::fromArray([
            'header' => [
                'channel' => self::CHANNEL,
                'correlation_id' => $orderId,
                'status' => $status,
            ],
            'data' => [],
        ]);
    }

    #[Test]
    public function build_creates_empty_group(): void
    {
        $group = Group::build();

        $this->assertSame([], $group->getOrders());
        $this->assertNotEmpty($group->getGroupId());
    }

    #[Test]
    public function build_with_tasks(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();

        $group = Group::build([$task1, $task2]);

        $this->assertCount(2, $group->getOrders());
    }

    #[Test]
    public function planify_order_adds_task(): void
    {
        $group = Group::build();
        $order = $this->makeOrderMessage();

        $group->planifyOrder($order);

        $this->assertTrue($group->has($order->getOrderId()));
        $this->assertCount(1, $group->getOrders());
    }

    #[Test]
    public function planify_adds_task(): void
    {
        $group = Group::build();
        $task = $this->makeTask();

        $group->planify($task);

        $this->assertTrue($group->has($task->getTaskId()));
    }

    #[Test]
    public function planify_workflow_adds_workflow(): void
    {
        $group = Group::build();
        $workflow = new Workflow();

        $group->planifyWorkflow($workflow);

        $this->assertTrue($group->has($workflow->getWorkflowId()));
    }

    #[Test]
    public function has_returns_false_for_unknown_order(): void
    {
        $group = Group::build();

        $this->assertFalse($group->has('non-existent-id'));
    }

    #[Test]
    public function find_returns_task(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $found = $group->find($task->getTaskId());

        $this->assertSame($task, $found);
    }

    #[Test]
    public function update_updates_task_status(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task->getTaskId());
        [$returnedGroup, $returnedTask] = $group->update($reply);

        $this->assertSame($group, $returnedGroup);
        $this->assertSame($task, $returnedTask);
        $this->assertTrue($task->isSuccess());
    }

    #[Test]
    public function retry_task_resets_task(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $task->getTaskId());
        $group->update($reply);

        [$returnedGroup, $returnedTask] = $group->retryTask($reply);

        $this->assertSame($group, $returnedGroup);
        $this->assertTrue($returnedTask->isPlanified());
        $this->assertSame(1, $returnedTask->getRetryCount());
    }

    #[Test]
    public function get_failed_orders_returns_only_failed_tasks(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();
        $group = Group::build([$task1, $task2]);

        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $task1->getTaskId()));
        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task2->getTaskId()));

        $failed = $group->getFailedOrders();

        $this->assertCount(1, $failed);
        $this->assertSame($task1, reset($failed));
    }

    #[Test]
    public function get_failed_orders_returns_empty_when_no_failures(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task->getTaskId()));

        $this->assertSame([], $group->getFailedOrders());
    }

    #[Test]
    public function get_errors_returns_errors_for_failed_tasks(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $reply = ReplyMessagePayload::fromArray([
            'header' => [
                'channel' => self::CHANNEL,
                'correlation_id' => $task->getTaskId(),
                'status' => MessageHandlerInterface::TYPE_ERROR,
            ],
            'data' => [
                'reply' => [
                    'error' => [
                        'exception' => ['message' => 'Something failed', 'code' => 500],
                        'context' => [],
                    ],
                ],
            ],
        ]);

        $group->update($reply);

        $errors = $group->getErrors();
        $this->assertCount(1, $errors);
        $this->assertContainsOnlyInstancesOf(Error::class, $errors);
    }

    #[Test]
    public function has_config_returns_false_by_default(): void
    {
        $group = Group::build();

        $this->assertFalse($group->hasConfig());
    }

    #[Test]
    public function has_config_returns_true_when_config_provided(): void
    {
        $groupConfig = new GroupConfig(retry: true, maxRetry: 5);
        $group = Group::build([], [], $groupConfig);

        $this->assertTrue($group->hasConfig());
        $this->assertSame(5, $group->getConfig()->getMaxRetry());
    }

    #[Test]
    public function can_retry_task_with_config(): void
    {
        $groupConfig = new GroupConfig(retry: true, maxRetry: 2);
        $task = $this->makeTask();
        $group = Group::build([$task], [], $groupConfig);

        $this->assertTrue($group->canRetryTask($task));

        $task->retry();
        $this->assertTrue($group->canRetryTask($task));

        $task->retry();
        $this->assertFalse($group->canRetryTask($task));
    }

    #[Test]
    public function can_retry_task_without_config_returns_false(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $this->assertFalse($group->canRetryTask($task));
    }

    #[Test]
    public function can_retry_task_when_retry_disabled(): void
    {
        $groupConfig = new GroupConfig(retry: false, maxRetry: 5);
        $task = $this->makeTask();
        $group = Group::build([$task], [], $groupConfig);

        $this->assertFalse($group->canRetryTask($task));
    }

    #[Test]
    public function get_progress_bag_returns_instance(): void
    {
        $group = Group::build([$this->makeTask()]);

        $this->assertInstanceOf(GroupProgressBag::class, $group->getProgressBag());
    }

    #[Test]
    public function get_status_returns_status(): void
    {
        $group = Group::build([$this->makeTask()]);

        $this->assertInstanceOf(Status::class, $group->getStatus());
        $this->assertTrue($group->getStatus()->isPending());
    }

    #[Test]
    public function get_percentage_returns_percentage(): void
    {
        $group = Group::build([$this->makeTask()]);

        $this->assertInstanceOf(Percentage::class, $group->getPercentage());
        $this->assertEqualsWithDelta(0.0, $group->getPercentage()->getCompleted(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function get_timer_returns_timer(): void
    {
        $group = Group::build([$this->makeTask()]);

        $this->assertInstanceOf(Timer::class, $group->getTimer());
    }

    #[Test]
    public function build_with_callbacks(): void
    {
        $called = false;
        $group = Group::build([], [
            BindableEventInterface::GROUP_ON_START => function () use (&$called): void {
                $called = true;
            },
        ]);

        $group->call(BindableEventInterface::GROUP_ON_START);
        $this->assertTrue($called);
    }

    #[Test]
    public function bind_invalid_event_type_throws_exception(): void
    {
        $group = Group::build();

        $this->expectException(\LogicException::class);
        $group->bind('invalid.event', function (): void {
        });
    }

    #[Test]
    public function group_percentage_after_completing_all_tasks(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();
        $group = Group::build([$task1, $task2]);

        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task1->getTaskId()));
        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task2->getTaskId()));

        $this->assertEqualsWithDelta(100.0, $group->getPercentage()->getCompleted(), PHP_FLOAT_EPSILON);
        $this->assertTrue($group->getStatus()->isSuccess());
    }

    #[Test]
    public function group_status_running_when_task_dispatched(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $task->setTaskAsDispatched();

        $this->assertTrue($group->getStatus()->isRunning());
    }

    #[Test]
    public function group_status_failed_when_task_failed(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $group->update($this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $task->getTaskId()));

        $this->assertTrue($group->getStatus()->isFailed());
    }

    #[Test]
    public function planify_returns_self_for_fluent_interface(): void
    {
        $group = Group::build();
        $result = $group->planify($this->makeTask());

        $this->assertSame($group, $result);
    }
}
