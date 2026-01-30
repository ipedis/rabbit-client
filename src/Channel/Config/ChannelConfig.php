<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Channel\Config;

use Ipedis\Rabbit\Channel\ChannelAbstract;

final readonly class ChannelConfig
{
    private string $channelName;

    public function __construct(string $channelName, private int $maxWorkers)
    {
        $this->channelName = ChannelAbstract::getTypeFromChannelName($channelName);
    }

    public static function build(string $channelName, int $maxWorkers): self
    {
        return new self($channelName, $maxWorkers);
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function getMaxWorkers(): int
    {
        return $this->maxWorkers;
    }
}
