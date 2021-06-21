<?php

namespace Ipedis\Rabbit\DTO\Store;

class TaskMeta
{
    /**
     * @var string
     */
    private $groupId;

    /**
     * @var string
     */
    private $workflowId;

    public function __construct(string $groupId, string $workflowId)
    {
        $this->groupId = $groupId;
        $this->workflowId = $workflowId;
    }

    /**
     * @param string $groupId
     * @param string $workflowId
     * @return static
     */
    public static function build(string $groupId, string $workflowId): self
    {
        return new self($groupId, $workflowId);
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * @return string
     */
    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }
}
