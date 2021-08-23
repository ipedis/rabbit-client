<?php

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;

$channelName = 'v1.dummy.some.channel';


/**
 * Section Factory - from Json
 */
it('must Build DTO', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName);
    $this->assertInstanceOf(EventMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});
it('must throw exception on not valid json', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromJson('not even a json');
});

it('must throw exception on empty json', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromJson('{}');
});

it('must throw exception when only json header key is present', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromJson('{"header": {"channel": "something"}}');
});

it('must throw exception when only json data key is present', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromJson('{"data": ""}');
});

it('must build from valid json', function () use ($channelName) {
    $event = EventMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "something"
	}
}');
    $this->assertInstanceOf(EventMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});


it('must throw exception on empty array', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromArray([]);
});

it('must throw exception when only array header key is present', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromArray(['header' => ['channel' => 'something']]);
});

it('must throw exception when only array data key is present', function () use ($channelName) {
    $this->expectException(MessagePayloadFormatException::class);
    EventMessagePayload::fromArray(['data' => []]);
});

it('must build from valid array', function () use ($channelName) {
    $event = EventMessagePayload::fromArray([
	'data' => [],
	'header' => [
		'channel' => 'something'
	]
]);
    $this->assertInstanceOf(EventMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});

/**
 * Section header
 */
it('must contain channel header by default', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName);
    $this->assertEquals($event->getChannel(), $channelName);
});

it('must not been parasitized by header parameter', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName, [], [
        MessagePayloadAbstract::HEADER_CHANNEL => 'something.else'
    ]);
    $this->assertEquals($event->getChannel(), $channelName);
});


it('contain uuid header by default', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName);
    $this->assertIsString($event->getUuid());
    $this->assertTrue(uuid_is_valid($event->getUuid()));
});

it('uuid can be defined on header parameter', function () use ($channelName) {
    $uuid = uuid_create();
    $event = EventMessagePayload::build($channelName, [], [
        MessagePayloadAbstract::HEADER_UUID => $uuid
    ]);
    $this->assertEquals($uuid, $event->getUuid());
});

it('contain sendAt timezone by default', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName);
    $this->assertIsInt($event->getTimestamp());
});

it('should return data encoded in json', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName, ['some' => 'data']);
    $this->assertJsonStringEqualsJsonString(json_encode(['some' => 'data']), $event->getStringifyData());
});

it('should return valid timezone name', function () use ($channelName) {
    $event = EventMessagePayload::build($channelName, ['some' => 'data']);
    var_dump($event->getTimezoneName());
    $this->assertEquals($event->getTimezoneName(), 'UTC');
});
