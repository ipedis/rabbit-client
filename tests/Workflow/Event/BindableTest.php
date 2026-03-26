<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\Event;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BindableTest extends TestCase
{
    private const CHANNEL = 'v1.service.aggregate.action';

    private const REPLY_QUEUE = 'reply.queue';

    #[Test]
    public function bind_and_call_single_callback(): void
    {
        $called = false;
        $workflow = new Workflow();
        $workflow->bind(BindableEventInterface::WORKFLOW_ON_START, function () use (&$called): void {
            $called = true;
        });

        $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        $this->assertTrue($called);
    }

    #[Test]
    public function bind_multiple_callbacks_to_same_event(): void
    {
        $count = 0;
        $workflow = new Workflow();
        $workflow->bind(BindableEventInterface::WORKFLOW_ON_START, function () use (&$count): void {
            ++$count;
        });
        $workflow->bind(BindableEventInterface::WORKFLOW_ON_START, function () use (&$count): void {
            ++$count;
        });

        $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function call_without_bound_callbacks_does_nothing(): void
    {
        $workflow = new Workflow();

        $result = $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        $this->assertSame($workflow, $result);
    }

    #[Test]
    public function call_without_payload_passes_event_type(): void
    {
        $receivedEventType = null;
        $workflow = new Workflow();
        $workflow->bind(BindableEventInterface::WORKFLOW_ON_START, function (string $eventType) use (&$receivedEventType): void {
            $receivedEventType = $eventType;
        });

        $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        $this->assertSame(BindableEventInterface::WORKFLOW_ON_START, $receivedEventType);
    }

    #[Test]
    public function call_with_payload_passes_payload_and_event_type(): void
    {
        $receivedPayload = null;
        $receivedEventType = null;
        $workflow = new Workflow();
        $workflow->bind(BindableEventInterface::WORKFLOW_ON_FINISH, function (mixed $payload, string $eventType) use (&$receivedPayload, &$receivedEventType): void {
            $receivedPayload = $payload;
            $receivedEventType = $eventType;
        });

        $workflow->call(BindableEventInterface::WORKFLOW_ON_FINISH, 'some payload');

        $this->assertSame('some payload', $receivedPayload);
        $this->assertSame(BindableEventInterface::WORKFLOW_ON_FINISH, $receivedEventType);
    }

    #[Test]
    public function call_error_event_with_task_includes_error_info(): void
    {
        $receivedError = null;
        $task = Task::build(OrderMessagePayload::build(self::CHANNEL, [], ['replyQueue' => self::REPLY_QUEUE]));

        // Give task a reply message so Serializer can extract error
        $reply = ReplyMessagePayload::fromArray([
            'header' => [
                'channel' => self::CHANNEL,
                'correlation_id' => $task->getTaskId(),
                'status' => MessageHandlerInterface::TYPE_ERROR,
            ],
            'data' => [
                'reply' => [
                    'error' => [
                        'exception' => ['message' => 'Test error', 'code' => 500],
                        'context' => [],
                    ],
                ],
            ],
        ]);
        $task->update($reply);

        $task->bind(BindableEventInterface::TASK_ON_FAILURE, function (mixed $payload, string $eventType, mixed $error) use (&$receivedError): void {
            $receivedError = $error;
        });

        $task->call(BindableEventInterface::TASK_ON_FAILURE, $task);

        $this->assertNotNull($receivedError);
    }

    #[Test]
    public function bind_invalid_event_type_throws(): void
    {
        $workflow = new Workflow();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('event type : invalid.type is not allowed');

        $workflow->bind('invalid.type', function (): void {
        });
    }

    #[Test]
    public function call_returns_self_for_fluent_interface(): void
    {
        $workflow = new Workflow();

        $result = $workflow->call(BindableEventInterface::WORKFLOW_ON_START);

        $this->assertSame($workflow, $result);
    }

    #[Test]
    public function bind_returns_self_for_fluent_interface(): void
    {
        $workflow = new Workflow();

        $result = $workflow->bind(BindableEventInterface::WORKFLOW_ON_START, function (): void {
        });

        $this->assertSame($workflow, $result);
    }

    #[Test]
    public function group_accepts_group_event_types(): void
    {
        $called = false;
        $group = Group::build();
        $group->bind(BindableEventInterface::GROUP_ON_FINISH, function () use (&$called): void {
            $called = true;
        });

        $group->call(BindableEventInterface::GROUP_ON_FINISH);
        $this->assertTrue($called);
    }

    #[Test]
    public function group_rejects_workflow_event_types(): void
    {
        $group = Group::build();

        $this->expectException(\LogicException::class);
        $group->bind(BindableEventInterface::WORKFLOW_ON_START, function (): void {
        });
    }

    #[Test]
    public function task_accepts_task_event_types(): void
    {
        $called = false;
        $task = Task::build(OrderMessagePayload::build(self::CHANNEL, [], ['replyQueue' => self::REPLY_QUEUE]));
        $task->bind(BindableEventInterface::TASK_ON_PROGRESS, function () use (&$called): void {
            $called = true;
        });

        $task->call(BindableEventInterface::TASK_ON_PROGRESS);
        $this->assertTrue($called);
    }

    #[Test]
    public function task_rejects_group_event_types(): void
    {
        $task = Task::build(OrderMessagePayload::build(self::CHANNEL, [], ['replyQueue' => self::REPLY_QUEUE]));

        $this->expectException(\LogicException::class);
        $task->bind(BindableEventInterface::GROUP_ON_START, function (): void {
        });
    }

    #[Test]
    public function assert_callbacks_with_invalid_callable_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not a callable');

        Group::build([], [
            BindableEventInterface::GROUP_ON_START => ['not a callable'],
        ]);
    }

    #[Test]
    public function assert_callbacks_with_invalid_event_type_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not allowed');

        Group::build([], [
            'invalid.type' => function (): void {
            },
        ]);
    }
}
