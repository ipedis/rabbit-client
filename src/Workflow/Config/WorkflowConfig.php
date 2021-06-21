<?php

namespace Ipedis\Rabbit\Workflow\Config;

use Ipedis\Rabbit\Channel\Config\ChannelConfig;
use Ipedis\Rabbit\Exception\Channel\InvalidChannelConfigException;

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

    /**
     * @var array
     */
    private $channelsConfig;

    public function __construct(
        bool $continueOnFailure = false,
        bool $hasToRetry = false,
        $maxRetry = 3,
        bool $ignoreParentHooks = false,
        array $channelsConfig = []
    ) {
        $this->retry = $hasToRetry;
        $this->maxRetry = $maxRetry;
        $this->continueOnFailure = $continueOnFailure;
        $this->ignoreParentHooks = $ignoreParentHooks;
        $this->setChannelsConfig($channelsConfig);
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

    /**
     * @param string $channelName
     * @return bool
     */
    public function hasConcurrencyLimitForChannel(string $channelName): bool
    {
        return $this->hasChannelConfig($channelName);
    }

    /**
     * @param string $channelName
     * @return int
     */
    public function getConcurrencyLimitForChannel(string $channelName): int
    {
        return $this
            ->getChannelConfig($channelName)
            ->getMaxWorkers()
        ;
    }

    /**
     * @param string $channelName
     * @return bool
     */
    public function hasChannelConfig(string $channelName): bool
    {
        return isset($this->channelsConfig[$channelName]);
    }

    /**
     * @param string $channelName
     * @return ChannelConfig
     */
    public function getChannelConfig(string $channelName): ChannelConfig
    {
        return $this->channelsConfig[$channelName];
    }

    /**
     * Set channel configs
     *
     * @param array $channelConfigs
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
}
