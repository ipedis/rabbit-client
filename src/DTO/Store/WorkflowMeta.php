<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\DTO\Store;

use Ipedis\Rabbit\Workflow\Workflow;

final readonly class WorkflowMeta
{
    public function __construct(
        private Workflow $workflow,
        private ?string $parent = null,
        private ?string $group = null
    ) {
    }

    public static function build(Workflow $workflow, ?string $parentWorkflow = null, ?string $parentGroup = null): self
    {
        return new self($workflow, $parentWorkflow, $parentGroup);
    }

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function hasGroup(): bool
    {
        return !is_null($this->group);
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function isRootWorkflow(): bool
    {
        return !$this->hasParent();
    }

    public function hasParent(): bool
    {
        return !is_null($this->parent);
    }
}
