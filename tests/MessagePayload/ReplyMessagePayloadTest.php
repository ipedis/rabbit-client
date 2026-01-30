<?php

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

$channelName = 'amq.gen-j-pzpTUoJdVrj_MU2__SWw';

it('must build from valid json', function () use ($channelName): void {
    $event = ReplyMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "'.$channelName.'",
        "correlation_id": "583e3241-0cfe-497c-84ae-2371aef74a7c",
        "status": "not_valid_one"
	}
}');
    $this->assertInstanceOf(ReplyMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});


it('must throw exception on empty array', function (): void {
    $this->expectException(MessagePayloadFormatException::class);
    ReplyMessagePayload::fromArray([]);
});

it('must throw exception when only array header key is present', function () use ($channelName): void {
    $this->expectException(MessagePayloadFormatException::class);
    ReplyMessagePayload::fromArray(['header' => [
        'channel' => $channelName,
        'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
        'status' => 'success'
    ]]);
});

it('must throw exception when only array data key is present', function (): void {
    $this->expectException(MessagePayloadFormatException::class);
    ReplyMessagePayload::fromArray(['data' => []]);
});

it('must throw exception when status is not valid', function () use ($channelName): void {
    $event = ReplyMessagePayload::fromArray([
        'data' => [],
        'header' => [
            'channel' => $channelName,
            'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
            'status' => 'not_valid_one'
        ]
    ]);
    $this->assertInstanceOf(ReplyMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});


it('must build from valid array', function () use ($channelName): void {
    $event = ReplyMessagePayload::fromArray([
        'data' => [],
        'header' => [
            'channel' => $channelName,
            'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
            'status' => 'success'
        ]
    ]);
    $this->assertInstanceOf(ReplyMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});
