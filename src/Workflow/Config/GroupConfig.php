<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\Config;

class GroupConfig
{
    public function __construct(private readonly bool $retry = false, private readonly int $maxRetry = 3)
    {
    }

    public function hasToRetry(): bool
    {
        return $this->retry;
    }

    public function getMaxRetry(): int
    {
        return $this->maxRetry;
    }
}
