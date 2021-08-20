<?php

use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

it('should be able to create DTO from string', function () {
    // happy path.
    $event = EventChannel::fromString('v1.foo.bar.test-case');
    $this->assertInstanceOf(EventChannel::class, $event);
    // 1. change version number.
    $event = EventChannel::fromString('v18.foo.bar.test-case');
    $this->assertInstanceOf(EventChannel::class, $event);
    // 2. complexe service name.
    $event = EventChannel::fromString('v1.scheduler-recovery.bar.test-case');
    $this->assertInstanceOf(EventChannel::class, $event);
    // 3. complexe aggregate name.
    $event = EventChannel::fromString('v1.foo.related-publication.test-case');
    $this->assertInstanceOf(EventChannel::class, $event);
    // 4. change complexe action naming.
    $event = EventChannel::fromString('v1.foo.bar.very-long-test-case');
    $this->assertInstanceOf(EventChannel::class, $event);
});

/**
 * Section version assertion.
 */
it('should failed when version is not valid', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v-1.foo.bar.test-case');
});

it('should failed when version is missing', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('foo.bar.test-case');
});

it('should failed when version is in uppercase', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('V1.foo.bar.test-case');
});

it('should fail when version is suffixed with alphabet', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1b.foo.bar.test-case');
});

/**
 * Section service assertion.
 */
it('should failed when service is not valid', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.wrong_service-name.aggregate.trustable-fact');
});

it('should failed when service is missing', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.aggregate.trustable-fact');
});

it('should failed when service is in uppercase', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.SERVICE.aggregate.trustable-fact');
});

it('should failed when service contain numerical char', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service2-name.aggregate.trustable-fact');
});

/**
 * Section aggregate assertion.
 */
it('should failed when aggregate is not valid', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.wrong_aggregate.trustable-fact');
});

it('should failed when aggregate is missing', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.trustable-fact');
});

it('should failed when aggregate is in uppercase', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.AGGregate.trustable-fact');
});

it('should failed when aggregate contain numerical char', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate2.trustable-fact');
});
/**
 * Section action assertion.
 */
it('should failed when action is not valid', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.wrong_trustable-fact');
});

it('should failed when action is missing', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate-name');
});

it('should failed when action is in uppercase', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.TRUSTABLE-fact');
});

it('should failed when action contain numerical char', function () {
    // happy path.
    $this->expectException(ChannelNamingException::class);
    EventChannel::fromString('v1.service-name.aggregate.trustable-2pac');
});

/**
 * Section get type for channel name
 */
it('should be able to return the type for a channel name', function () {
    // happy path.
    $type = EventChannel::getTypeFromChannelName('v1.service.aggregate.action-name');
    $this->assertIsString($type);
    $this->assertEquals('service.aggregate.action-name', $type);
});

it('should throw exception when provided channel is not valid', function () {
    $this->expectException(ChannelNamingException::class);
    EventChannel::getTypeFromChannelName('v1.1service.aggregate.action-name');
});

it('should be convertable to string via __toString', function () {
    $this->expectException(ChannelNamingException::class);
    $event = EventChannel::fromString('v1.1service.aggregate.action-name');
    $this->assertEquals('v1.1service.aggregate.action-name', (string) $event);
});
