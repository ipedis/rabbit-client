<?php

namespace Ipedis\Rabbit\Channel\Config;

use Ipedis\Rabbit\Channel\ChannelAbstract;

final class ChannelConfig
{
    /**
     * @var string
     */
    private $channelName;

    /**
     * @var int
     */
    private $maxWorkers;

    public function __construct(string $channelName, int $maxWorkers)
    {
        $this->channelName = ChannelAbstract::getTypeFromChannelName($channelName);
        $this->maxWorkers = $maxWorkers;
    }

    public static function build(string $channelName, int $maxWorkers): self
    {
        return new self($channelName, $maxWorkers);
    }

    /**
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }

    /**
     * @return int
     */
    public function getMaxWorkers(): int
    {
        return $this->maxWorkers;
    }
}
