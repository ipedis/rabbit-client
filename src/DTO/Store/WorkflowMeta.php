<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\DTO\Store;

use Ipedis\Rabbit\Workflow\Workflow;

class WorkflowMeta
{
    public function __construct(
        private readonly Workflow $workflow,
        /**
         * @var string
         */
        private readonly ?string $parent = null,
        /**
         * @var string
         */
        private readonly ?string $group = null
    )
    {
    }

    /**
     * @param string $parentWorkflow
     * @param string $parentGroup
     * @return static
     */
    public static function build(Workflow $workflow, ?string $parentWorkflow = null, ?string $parentGroup = null): self
    {
        return new self($workflow, $parentWorkflow, $parentGroup);
    }

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function hasGroup(): bool
    {
        return !is_null($this->group);
    }

    public function getGroup(): string
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
