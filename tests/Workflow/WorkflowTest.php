<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow;

use Exception;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ipedis\Rabbit\Workflow\Config\GroupConfig;

final class WorkflowTest extends TestCase
{
    private const CHANNEL = 'v1.service.aggregate.action';

    private const REPLY_QUEUE = 'reply.queue';

    private function makeTask(): Task
    {
        return Task::build(OrderMessagePayload::build(self::CHANNEL, ['key' => 'value'], [
            'replyQueue' => self::REPLY_QUEUE,
        ]));
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
    public function construct_empty_workflow(): void
    {
        $workflow = new Workflow();

        $this->assertSame([], $workflow->getGroups());
        $this->assertNotEmpty($workflow->getWorkflowId());
    }

    #[Test]
    public function construct_with_custom_workflow_id(): void
    {
        /** @var string $uuid */
        $uuid = uuid_create();
        $workflow = new Workflow(workflowId: $uuid);

        $this->assertSame($uuid, $workflow->getWorkflowId());
    }

    #[Test]
    public function construct_with_invalid_workflow_id_throws_exception(): void
    {
        $this->expectException(InvalidUuidException::class);
        new Workflow(workflowId: 'not-a-uuid');
    }

    #[Test]
    public function construct_with_group_as_first_step(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);

        $workflow = new Workflow($group);

        $this->assertCount(1, $workflow->getGroups());
        $this->assertSame($group, $workflow->getGroups()[0]);
    }

    #[Test]
    public function construct_with_callable_as_first_step(): void
    {
        $task = $this->makeTask();

        $workflow = new Workflow(function (Group $group) use ($task): void {
            $group->planify($task);
        });

        $this->assertCount(1, $workflow->getGroups());
        $this->assertTrue($workflow->getGroups()[0]->has($task->getTaskId()));
    }

    #[Test]
    public function construct_with_callable_returning_group(): void
    {
        $task = $this->makeTask();

        $workflow = new Workflow(function (Group $group) use ($task): Group {
            $group->planify($task);
            return $group;
        });

        $this->assertCount(1, $workflow->getGroups());
        $this->assertTrue($workflow->getGroups()[0]->has($task->getTaskId()));
    }

    #[Test]
    public function then_adds_groups_sequentially(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();

        $workflow = new Workflow(Group::build([$task1]));
        $result = $workflow->then(Group::build([$task2]));

        $this->assertSame($workflow, $result);
        $this->assertCount(2, $workflow->getGroups());
    }

    #[Test]
    public function then_with_callable(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();

        $workflow = new Workflow(Group::build([$task1]));
        $workflow->then(function (Group $group) use ($task2): void {
            $group->planify($task2);
        });

        $this->assertCount(2, $workflow->getGroups());
        $this->assertTrue($workflow->getGroups()[1]->has($task2->getTaskId()));
    }

    #[Test]
    public function task_reply_updates_correct_task(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group);

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task->getTaskId());
        [$returnedGroup, $returnedTask] = $workflow->taskReply($reply);

        $this->assertSame($group, $returnedGroup);
        $this->assertSame($task, $returnedTask);
        $this->assertTrue($task->isSuccess());
    }

    #[Test]
    public function task_reply_throws_when_order_not_found(): void
    {
        $workflow = new Workflow(Group::build([$this->makeTask()]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No group found for order non-existent-id');

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, 'non-existent-id');
        $workflow->taskReply($reply);
    }

    #[Test]
    public function retry_group_task_resets_task(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group);

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_ERROR, $task->getTaskId());
        $workflow->taskReply($reply);

        [$returnedGroup, $returnedTask] = $workflow->retryGroupTask($reply);

        $this->assertSame($group, $returnedGroup);
        $this->assertTrue($returnedTask->isPlanified());
        $this->assertSame(1, $returnedTask->getRetryCount());
    }

    #[Test]
    public function retry_group_task_throws_when_order_not_found(): void
    {
        $workflow = new Workflow(Group::build([$this->makeTask()]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No group found for order non-existent-id');

        $reply = $this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, 'non-existent-id');
        $workflow->retryGroupTask($reply);
    }

    #[Test]
    public function find_group_returns_correct_group(): void
    {
        $group = Group::build([$this->makeTask()]);
        $workflow = new Workflow($group);

        $found = $workflow->findGroup($group->getGroupId());

        $this->assertSame($group, $found);
    }

    #[Test]
    public function find_group_throws_when_not_found(): void
    {
        $workflow = new Workflow(Group::build());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Group not found');

        $workflow->findGroup('non-existent-group');
    }

    #[Test]
    public function find_returns_correct_task(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group);

        $found = $workflow->find($task->getTaskId());

        $this->assertSame($task, $found);
    }

    #[Test]
    public function find_throws_when_task_not_found(): void
    {
        $workflow = new Workflow(Group::build([$this->makeTask()]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Task not found');

        $workflow->find('non-existent-task');
    }

    #[Test]
    public function get_config_returns_default_config(): void
    {
        $workflow = new Workflow();

        $config = $workflow->getConfig();

        $this->assertInstanceOf(WorkflowConfig::class, $config);
        $this->assertFalse($config->hasToRetry());
        $this->assertFalse($config->hasToContinueOnFailure());
    }

    #[Test]
    public function set_config_replaces_config(): void
    {
        $workflow = new Workflow();
        $workflowConfig = new WorkflowConfig(retry: true, maxRetry: 5);

        $result = $workflow->setConfig($workflowConfig);

        $this->assertSame($workflow, $result);
        $this->assertTrue($workflow->getConfig()->hasToRetry());
        $this->assertSame(5, $workflow->getConfig()->getMaxRetry());
    }

    #[Test]
    public function can_retry_task_with_workflow_config(): void
    {
        $workflowConfig = new WorkflowConfig(retry: true, maxRetry: 2);
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group, config: $workflowConfig);

        $this->assertTrue($workflow->canRetryTask($task, $group));
    }

    #[Test]
    public function can_retry_task_returns_false_when_group_has_config(): void
    {
        $workflowConfig = new WorkflowConfig(retry: true, maxRetry: 5);
        $groupConfig = new GroupConfig(retry: true, maxRetry: 3);
        $task = $this->makeTask();
        $group = Group::build([$task], [], $groupConfig);
        $workflow = new Workflow($group, config: $workflowConfig);

        // When group has its own config, workflow-level retry is disabled
        $this->assertFalse($workflow->canRetryTask($task, $group));
    }

    #[Test]
    public function can_retry_task_returns_false_when_max_retries_reached(): void
    {
        $workflowConfig = new WorkflowConfig(retry: true, maxRetry: 1);
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group, config: $workflowConfig);

        $task->retry();
        $this->assertFalse($workflow->canRetryTask($task, $group));
    }

    #[Test]
    public function get_progress_percentage(): void
    {
        $task = $this->makeTask();
        $group = Group::build([$task]);
        $workflow = new Workflow($group);

        $this->assertEqualsWithDelta(0.0, $workflow->getProgressPercentage(), PHP_FLOAT_EPSILON);

        $workflow->taskReply($this->makeReplyMessage(MessageHandlerInterface::TYPE_SUCCESS, $task->getTaskId()));

        $this->assertEqualsWithDelta(100.0, $workflow->getProgressPercentage(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function get_progress_bag_returns_instance(): void
    {
        $workflow = new Workflow(Group::build([$this->makeTask()]));

        $this->assertInstanceOf(WorkflowProgressBag::class, $workflow->getProgressBag());
    }

    #[Test]
    public function get_errors_returns_errors_from_all_groups(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();
        $workflow = new Workflow(Group::build([$task1]));
        $workflow->then(Group::build([$task2]));

        $reply1 = ReplyMessagePayload::fromArray([
            'header' => [
                'channel' => self::CHANNEL,
                'correlation_id' => $task1->getTaskId(),
                'status' => MessageHandlerInterface::TYPE_ERROR,
            ],
            'data' => [
                'reply' => [
                    'error' => [
                        'exception' => ['message' => 'Error 1', 'code' => 500],
                        'context' => [],
                    ],
                ],
            ],
        ]);

        $workflow->taskReply($reply1);

        $errors = $workflow->getErrors();
        $this->assertCount(1, $errors);
    }

    #[Test]
    public function workflow_with_group_callbacks(): void
    {
        $called = false;

        $workflow = new Workflow(
            Group::build(),
            [BindableEventInterface::GROUP_ON_START => function () use (&$called): void {
                $called = true;
            }]
        );

        $workflow->getGroups()[0]->call(BindableEventInterface::GROUP_ON_START);
        $this->assertTrue($called);
    }

    #[Test]
    public function workflow_bind_valid_event(): void
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
    public function workflow_bind_invalid_event_throws(): void
    {
        $workflow = new Workflow();

        $this->expectException(\LogicException::class);
        $workflow->bind('invalid.event', function (): void {
        });
    }

    #[Test]
    public function find_across_multiple_groups(): void
    {
        $task1 = $this->makeTask();
        $task2 = $this->makeTask();

        $workflow = new Workflow(Group::build([$task1]));
        $workflow->then(Group::build([$task2]));

        $this->assertSame($task2, $workflow->find($task2->getTaskId()));
    }
}
