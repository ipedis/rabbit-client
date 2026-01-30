<?php

use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

it('should be able to create EventChannel from string', function (string $eventName): void {
    $this->assertInstanceOf(
        EventChannel::class,
        EventChannel::fromString($eventName)
    );
})->with([
    // happy path.
    'v1.foo.bar.test-case',
    // 1. change version number.
    'v18.foo.bar.test-case',
    // 2. complexe service name.
    'v1.scheduler-recovery.bar.test-case',
    // 3. complexe aggregate name.
    'v1.foo.related-publication.test-case',
    // 4. change complexe action naming.
    'v1.foo.bar.very-long-test-case',
]);

/**
 * Section version assertion.
 */
it('should failed when version is not valid', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v-1.foo.bar.test-case');
});

it('should failed when version is missing', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('foo.bar.test-case');
});

it('should failed when version is in uppercase', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('V1.foo.bar.test-case');
});

it('should fail when version is suffixed with alphabet', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1b.foo.bar.test-case');
});

/**
 * Section service assertion.
 */
it('should failed when service is not valid', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.wrong_service-name.aggregate.trustable-fact');
});

it('should failed when service is missing', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.aggregate.trustable-fact');
});

it('should failed when service is in uppercase', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.SERVICE.aggregate.trustable-fact');
});

it('should failed when service contain numerical char', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service2-name.aggregate.trustable-fact');
});

/**
 * Section aggregate assertion.
 */
it('should failed when aggregate is not valid', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.wrong_aggregate.trustable-fact');
});

it('should failed when aggregate is missing', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.trustable-fact');
});

it('should failed when aggregate is in uppercase', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.AGGregate.trustable-fact');
});

it('should failed when aggregate contain numerical char', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate2.trustable-fact');
});
/**
 * Section action assertion.
 */
it('should failed when action is not valid', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.wrong_trustable-fact');
});

it('should failed when action is missing', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate-name');
});

it('should failed when action is in uppercase', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.TRUSTABLE-fact');
});

it('should failed when action contain numerical char', function (): void {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.trustable-2pac');
});

/**
 * Section get type for channel name
 */
it('should be able to return the type for a channel name', function (): void {
    // happy path.
    $type = EventChannel::getTypeFromChannelName('v1.service.aggregate.action-name');
    $this->assertIsString($type);
    $this->assertEquals('service.aggregate.action-name', $type);
});

it('should throw exception when provided channel is not valid', function (): void {
    $this->expectException(ChannelNamingException::class);
    EventChannel::getTypeFromChannelName('v1.1service.aggregate.action-name');
});

it('should be convertable to string via __toString', function (): void {
    $this->expectException(ChannelNamingException::class);
    $event = EventChannel::fromString('v1.1service.aggregate.action-name');
    $this->assertEquals('v1.1service.aggregate.action-name', (string) $event);
});
