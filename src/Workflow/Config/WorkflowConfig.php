<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\Config;

use Ipedis\Rabbit\Channel\Config\ChannelConfig;
use Ipedis\Rabbit\Exception\Channel\InvalidChannelConfigException;

class WorkflowConfig
{
    private array $channelsConfig;

    public function __construct(
        private readonly bool $continueOnFailure = false,
        private readonly bool $retry = false,
        private readonly int $maxRetry = 3,
        private readonly bool $ignoreParentHooks = false,
        array $channelsConfig = []
    ) {
        $this->setChannelsConfig($channelsConfig);
    }

    /**
     * Set channel configs
     *
     * @throws InvalidChannelConfigException
     */
    protected function setChannelsConfig(array $channelConfigs)
    {
        foreach ($channelConfigs as $channelConfig) {
            if (!($channelConfig instanceof ChannelConfig)) {
                throw new InvalidChannelConfigException('Invalid channel config supplied for workflow');
            }

            $this->channelsConfig[$channelConfig->getChannelName()] = $channelConfig;
        }
    }

    public function hasToRetry(): bool
    {
        return $this->retry;
    }

    public function getMaxRetry(): int
    {
        return $this->maxRetry;
    }

    public function hasToContinueOnFailure(): bool
    {
        return $this->continueOnFailure;
    }

    public function ignoreParentHooks(): bool
    {
        return $this->ignoreParentHooks;
    }

    public function hasConcurrencyLimitForChannel(string $channelName): bool
    {
        return $this->hasChannelConfig($channelName);
    }

    public function hasChannelConfig(string $channelName): bool
    {
        return isset($this->channelsConfig[$channelName]);
    }

    public function getConcurrencyLimitForChannel(string $channelName): int
    {
        return $this
            ->getChannelConfig($channelName)
            ->getMaxWorkers();
    }

    public function getChannelConfig(string $channelName): ChannelConfig
    {
        return $this->channelsConfig[$channelName];
    }
}
