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


it('must throw exception on invalid json', fn ($value) => EventMessagePayload::fromJson($value))
    ->with([
        'not even a json',
        '{}', // empty json.
        '{"header": {"channel": "something"}}', // only header json key is present
        '{"data": ""}' // only data json key is present.
    ])->throws(MessagePayloadFormatException::class);

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

it('must throw exception on empty array', fn ($value = []) => EventMessagePayload::fromArray($value))
    ->with([
        [], // empty array
        ['header' => ['channel' => 'something']], // only header json key is present
        ['data' => []],  // only data json key is present.
    ])->throws(MessagePayloadFormatException::class);

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
    $this->assertEquals($event->getTimezoneName(), 'UTC');
});
