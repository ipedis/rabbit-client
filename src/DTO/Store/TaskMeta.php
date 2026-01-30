<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\DTO\Store;

class TaskMeta
{
    public function __construct(private readonly string $groupId, private readonly string $workflowId)
    {
    }

    /**
     * @return static
     */
    public static function build(string $groupId, string $workflowId): self
    {
        return new self($groupId, $workflowId);
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }
}
