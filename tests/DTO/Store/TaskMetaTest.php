<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\DTO\Store;

use Ipedis\Rabbit\DTO\Store\TaskMeta;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaskMetaTest extends TestCase
{
    #[Test]
    public function build_creates_task_meta(): void
    {
        $meta = TaskMeta::build('group-123', 'workflow-456');

        $this->assertSame('group-123', $meta->getGroupId());
        $this->assertSame('workflow-456', $meta->getWorkflowId());
    }

    #[Test]
    public function constructor_creates_task_meta(): void
    {
        $taskMeta = new TaskMeta('group-abc', 'workflow-xyz');

        $this->assertSame('group-abc', $taskMeta->getGroupId());
        $this->assertSame('workflow-xyz', $taskMeta->getWorkflowId());
    }
}
