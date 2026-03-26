<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Channel;

use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderChannelTest extends TestCase
{
    #[Test]
    public function build_creates_valid_channel(): void
    {
        $channel = OrderChannel::build('v1', 'service', 'aggregate', 'action');

        $this->assertSame('v1', $channel->getProtocol());
        $this->assertSame('service', $channel->getService());
        $this->assertSame('aggregate', $channel->getAggregate());
        $this->assertSame('action', $channel->getAction());
        $this->assertSame('v1.service.aggregate.action', (string) $channel);
    }

    #[Test]
    public function from_string_parses_valid_channel(): void
    {
        $channel = OrderChannel::fromString('v1.my-service.my-aggregate.my-action');

        $this->assertSame('v1', $channel->getProtocol());
        $this->assertSame('my-service', $channel->getService());
        $this->assertSame('my-aggregate', $channel->getAggregate());
        $this->assertSame('my-action', $channel->getAction());
    }

    #[Test]
    public function from_string_with_invalid_channel_throws(): void
    {
        $this->expectException(ChannelNamingException::class);

        OrderChannel::fromString('invalid');
    }

    #[Test]
    public function build_with_invalid_protocol_throws(): void
    {
        $this->expectException(ChannelNamingException::class);

        OrderChannel::build('V1', 'service', 'aggregate', 'action');
    }

    #[Test]
    public function get_type_from_channel_name(): void
    {
        $type = OrderChannel::getTypeFromChannelName('v1.service.aggregate.action');

        $this->assertSame('service.aggregate.action', $type);
    }
}
