<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\DTO\Store;

final readonly class TaskMeta
{
    public function __construct(private string $groupId, private string $workflowId)
    {
    }

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
