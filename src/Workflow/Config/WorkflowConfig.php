<?php


namespace Ipedis\Rabbit\Workflow\Config;


class WorkflowConfig
{
    /**
     * @var bool
     */
    private $retry;

    /**
     * @var int
     */
    private $maxRetry;

    /**
     * @var bool
     */
    private $continueOnFailure;

    /**
     * @var bool
     */
    private $ignoreParentHooks;

    public function __construct(
        bool $continueOnFailure = false,
        bool $hasToRetry = false,
        $maxRetry = 3,
        bool $ignoreParentHooks = false
    ) {
        $this->retry = $hasToRetry;
        $this->maxRetry = $maxRetry;
        $this->continueOnFailure = $continueOnFailure;
        $this->ignoreParentHooks = $ignoreParentHooks;
    }

    public function hasToRetry(): bool
    {
        return $this->retry;
    }

    /**
     * @return int
     */
    public function getMaxRetry(): int
    {
        return $this->maxRetry;
    }

    /**
     * @return bool
     */
    public function hasToContinueOnFailure(): bool
    {
        return $this->continueOnFailure;
    }

    /**
     * @return bool
     */
    public function ignoreParentHooks(): bool
    {
        return $this->ignoreParentHooks;
    }
}
