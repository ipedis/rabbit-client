<?php


namespace Ipedis\Rabbit\Workflow\Config;


class GroupConfig
{
    /**
     * @var bool
     */
    private $retry;
    /**
     * @var int
     */
    private $maxRetry;

    public function __construct(bool $hasToRetry = false, $maxRetry = 3)
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
