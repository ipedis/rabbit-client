<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\DTO\Store;

use Ipedis\Rabbit\DTO\Store\WorkflowMeta;
use Ipedis\Rabbit\Workflow\Workflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowMetaTest extends TestCase
{
    #[Test]
    public function build_root_workflow(): void
    {
        $workflow = new Workflow();
        $meta = WorkflowMeta::build($workflow);

        $this->assertSame($workflow, $meta->getWorkflow());
        $this->assertNull($meta->getParent());
        $this->assertNull($meta->getGroup());
        $this->assertTrue($meta->isRootWorkflow());
        $this->assertFalse($meta->hasParent());
        $this->assertFalse($meta->hasGroup());
    }

    #[Test]
    public function build_child_workflow_with_parent(): void
    {
        $workflow = new Workflow();
        $meta = WorkflowMeta::build($workflow, 'parent-workflow-id');

        $this->assertSame('parent-workflow-id', $meta->getParent());
        $this->assertFalse($meta->isRootWorkflow());
        $this->assertTrue($meta->hasParent());
    }

    #[Test]
    public function build_child_workflow_with_group(): void
    {
        $workflow = new Workflow();
        $meta = WorkflowMeta::build($workflow, 'parent-id', 'group-id');

        $this->assertSame('parent-id', $meta->getParent());
        $this->assertSame('group-id', $meta->getGroup());
        $this->assertTrue($meta->hasParent());
        $this->assertTrue($meta->hasGroup());
    }

    #[Test]
    public function constructor_with_nulls(): void
    {
        $workflow = new Workflow();
        $workflowMeta = new WorkflowMeta($workflow);

        $this->assertTrue($workflowMeta->isRootWorkflow());
        $this->assertFalse($workflowMeta->hasGroup());
    }
}
