<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Channel\Config;

use Ipedis\Rabbit\Channel\Config\ChannelConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChannelConfigTest extends TestCase
{
    #[Test]
    public function build_creates_config(): void
    {
        $config = ChannelConfig::build('v1.service.aggregate.action', 5);

        $this->assertSame('service.aggregate.action', $config->getChannelName());
        $this->assertSame(5, $config->getMaxWorkers());
    }

    #[Test]
    public function constructor_extracts_type_from_channel_name(): void
    {
        $channelConfig = new ChannelConfig('v1.my-service.my-aggregate.my-action', 10);

        $this->assertSame('my-service.my-aggregate.my-action', $channelConfig->getChannelName());
        $this->assertSame(10, $channelConfig->getMaxWorkers());
    }
}
