<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\Config;

use Ipedis\Rabbit\Channel\Config\ChannelConfig;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowConfigTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $workflowConfig = new WorkflowConfig();

        $this->assertFalse($workflowConfig->hasToContinueOnFailure());
        $this->assertFalse($workflowConfig->hasToRetry());
        $this->assertSame(3, $workflowConfig->getMaxRetry());
        $this->assertFalse($workflowConfig->ignoreParentHooks());
    }

    #[Test]
    public function custom_values(): void
    {
        $workflowConfig = new WorkflowConfig(
            continueOnFailure: true,
            retry: true,
            maxRetry: 10,
            ignoreParentHooks: true
        );

        $this->assertTrue($workflowConfig->hasToContinueOnFailure());
        $this->assertTrue($workflowConfig->hasToRetry());
        $this->assertSame(10, $workflowConfig->getMaxRetry());
        $this->assertTrue($workflowConfig->ignoreParentHooks());
    }

    #[Test]
    public function has_channel_config_returns_false_when_no_config(): void
    {
        $workflowConfig = new WorkflowConfig();

        $this->assertFalse($workflowConfig->hasChannelConfig('v1.service.aggregate.action'));
        $this->assertFalse($workflowConfig->hasConcurrencyLimitForChannel('v1.service.aggregate.action'));
    }

    #[Test]
    public function channel_config_is_stored_by_type(): void
    {
        $channelConfig = ChannelConfig::build('v1.service.aggregate.action', 5);
        $workflowConfig = new WorkflowConfig(channelsConfig: [$channelConfig]);

        $channelType = $channelConfig->getChannelName();
        $this->assertTrue($workflowConfig->hasChannelConfig($channelType));
        $this->assertTrue($workflowConfig->hasConcurrencyLimitForChannel($channelType));
        $this->assertSame(5, $workflowConfig->getConcurrencyLimitForChannel($channelType));
        $this->assertSame($channelConfig, $workflowConfig->getChannelConfig($channelType));
    }

    #[Test]
    public function multiple_channel_configs(): void
    {
        $config1 = ChannelConfig::build('v1.service.aggregate.action', 3);
        $config2 = ChannelConfig::build('v1.service.aggregate.other', 7);

        $workflowConfig = new WorkflowConfig(channelsConfig: [$config1, $config2]);

        $this->assertTrue($workflowConfig->hasChannelConfig($config1->getChannelName()));
        $this->assertTrue($workflowConfig->hasChannelConfig($config2->getChannelName()));
        $this->assertSame(3, $workflowConfig->getConcurrencyLimitForChannel($config1->getChannelName()));
        $this->assertSame(7, $workflowConfig->getConcurrencyLimitForChannel($config2->getChannelName()));
    }
}
