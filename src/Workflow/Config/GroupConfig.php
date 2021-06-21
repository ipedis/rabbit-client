<?php

namespace Ipedis\Rabbit\Workflow\Config;

class GroupConfig
{
    /**
     * @var bool
     */
    private bool $retry;

    /**
     * @var int
     */
    private int $maxRetry;

    public function __construct(bool $hasToRetry = false, int $maxRetry = 3)
    {
        $this->retry = $hasToRetry;
        $this->maxRetry = $maxRetry;
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
}
