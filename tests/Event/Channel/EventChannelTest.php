<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Event\Channel;

use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventChannelTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string}>
     */
    public static function validChannelProvider(): \Iterator
    {
        yield 'happy path' => ['v1.foo.bar.test-case'];
        yield 'change version number' => ['v18.foo.bar.test-case'];
        yield 'complex service name' => ['v1.scheduler-recovery.bar.test-case'];
        yield 'complex aggregate name' => ['v1.foo.related-publication.test-case'];
        yield 'complex action naming' => ['v1.foo.bar.very-long-test-case'];
    }

    #[Test]
    #[DataProvider('validChannelProvider')]
    public function it_should_be_able_to_create_event_channel_from_string(string $eventName): void
    {
        $this->assertInstanceOf(
            EventChannel::class,
            EventChannel::fromString($eventName)
        );
    }

    #[Test]
    public function it_should_failed_when_version_is_not_valid(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v-1.foo.bar.test-case');
    }

    #[Test]
    public function it_should_failed_when_version_is_missing(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('foo.bar.test-case');
    }

    #[Test]
    public function it_should_failed_when_version_is_in_uppercase(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('V1.foo.bar.test-case');
    }

    #[Test]
    public function it_should_fail_when_version_is_suffixed_with_alphabet(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1b.foo.bar.test-case');
    }

    #[Test]
    public function it_should_failed_when_service_is_not_valid(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.wrong_service-name.aggregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_service_is_missing(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.aggregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_service_is_in_uppercase(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.SERVICE.aggregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_service_contain_numerical_char(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service2-name.aggregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_aggregate_is_not_valid(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.wrong_aggregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_aggregate_is_missing(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_aggregate_is_in_uppercase(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.AGGregate.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_aggregate_contain_numerical_char(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.aggregate2.trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_action_is_not_valid(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.aggregate.wrong_trustable-fact');
    }

    #[Test]
    public function it_should_failed_when_action_is_missing(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.aggregate-name');
    }

    #[Test]
    public function it_should_failed_when_action_is_in_uppercase(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.aggregate.TRUSTABLE-fact');
    }

    #[Test]
    public function it_should_failed_when_action_contain_numerical_char(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::fromString('v1.service-name.aggregate.trustable-2pac');
    }

    #[Test]
    public function it_should_be_able_to_return_the_type_for_a_channel_name(): void
    {
        $type = EventChannel::getTypeFromChannelName('v1.service.aggregate.action-name');
        $this->assertIsString($type);
        $this->assertSame('service.aggregate.action-name', $type);
    }

    #[Test]
    public function it_should_throw_exception_when_provided_channel_is_not_valid(): void
    {
        $this->expectException(ChannelNamingException::class);
        EventChannel::getTypeFromChannelName('v1.1service.aggregate.action-name');
    }

    #[Test]
    public function it_should_be_convertable_to_string_via_to_string(): void
    {
        $this->expectException(ChannelNamingException::class);
        $event = EventChannel::fromString('v1.1service.aggregate.action-name');
        $this->assertSame('v1.1service.aggregate.action-name', (string) $event);
    }
}
