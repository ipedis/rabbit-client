<?php

namespace Ipedis\Rabbit\DTO\Store;

use Ipedis\Rabbit\Workflow\Workflow;

class WorkflowMeta
{
    /**
     * @var Workflow
     */
    private $workflow;

    /**
     * @var string
     */
    private $parent;

    /**
     * @var string
     */
    private $group;

    public function __construct(Workflow $workflow, ?string $parentWorkflow = null, ?string $parentGroup = null)
    {
        $this->workflow = $workflow;
        $this->parent = $parentWorkflow;
        $this->group = $parentGroup;
    }

    /**
     * @param Workflow $workflow
     * @param string $parentWorkflow
     * @param string $parentGroup
     * @return static
     */
    public static function build(Workflow $workflow, ?string $parentWorkflow = null, ?string $parentGroup = null): self
    {
        return new self($workflow, $parentWorkflow, $parentGroup);
    }

    /**
     * @return Workflow
     */
    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    /**
     * @return string
     */
    public function getParent(): string
    {
        return $this->parent;
    }

    /**
     * @return bool
     */
    public function hasGroup(): bool
    {
        return !is_null($this->group);
    }

    /**
     * @return string
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @return bool
     */
    public function isRootWorkflow(): bool
    {
        return !$this->hasParent();
    }

    /**
     * @return bool
     */
    public function hasParent(): bool
    {
        return !is_null($this->parent);
    }
}
