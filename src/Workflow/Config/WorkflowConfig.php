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

    public function __construct(bool $continueOnFailure = false, bool $hasToRetry = false, $maxRetry = 3)
    {
        $this->retry = $hasToRetry;
        $this->maxRetry = $maxRetry;
        $this->continueOnFailure = $continueOnFailure;
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
}
