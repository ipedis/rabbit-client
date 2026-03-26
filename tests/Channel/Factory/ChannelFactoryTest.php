<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Channel\Factory;

use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChannelFactoryTest extends TestCase
{
    #[Test]
    public function get_event_creates_event_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $channel = $channelFactory->getEvent('aggregate.action');

        $this->assertInstanceOf(EventChannel::class, $channel);
        $this->assertSame('v1.my-service.aggregate.action', (string) $channel);
    }

    #[Test]
    public function get_event_with_custom_protocol(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $channel = $channelFactory->getEvent('aggregate.action', 'v2');

        $this->assertSame('v2.my-service.aggregate.action', (string) $channel);
    }

    #[Test]
    public function get_event_with_invalid_partial_throws(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->expectException(ChannelNamingException::class);

        $channelFactory->getEvent('invalid');
    }

    #[Test]
    public function get_order_creates_order_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $channel = $channelFactory->getOrder('aggregate.action');

        $this->assertInstanceOf(OrderChannel::class, $channel);
        $this->assertSame('v1.my-service.aggregate.action', (string) $channel);
    }

    #[Test]
    public function get_order_with_custom_protocol(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $channel = $channelFactory->getOrder('aggregate.action', 'v3');

        $this->assertSame('v3.my-service.aggregate.action', (string) $channel);
    }

    #[Test]
    public function get_order_with_invalid_partial_throws(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->expectException(ChannelNamingException::class);

        $channelFactory->getOrder('no-dots-here');
    }

    #[Test]
    public function match_partial_with_valid_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->assertTrue($channelFactory->matchPartial('aggregate.action'));
        $this->assertTrue($channelFactory->matchPartial('my-aggregate.my-action'));
    }

    #[Test]
    public function match_partial_with_invalid_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->assertFalse($channelFactory->matchPartial('no-dots'));
        $this->assertFalse($channelFactory->matchPartial(''));
    }

    #[Test]
    public function match_with_valid_full_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->assertTrue($channelFactory->match('v1.service.aggregate.action'));
        $this->assertTrue($channelFactory->match('v2.my-service.my-aggregate.my-action'));
    }

    #[Test]
    public function match_with_invalid_full_channel(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->assertFalse($channelFactory->match('aggregate.action'));
        $this->assertFalse($channelFactory->match(''));
        $this->assertFalse($channelFactory->match('v1.service'));
    }

    #[Test]
    public function get_event_with_hyphenated_aggregate(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $channel = $channelFactory->getEvent('my-aggregate.my-action');

        $this->assertSame('my-aggregate', $channel->getAggregate());
        $this->assertSame('my-action', $channel->getAction());
    }

    #[Test]
    public function get_order_with_empty_partial_throws(): void
    {
        $channelFactory = new ChannelFactory('v1', 'my-service');

        $this->expectException(ChannelNamingException::class);

        $channelFactory->getOrder('');
    }
}
