<?php

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;

$replyQueue = 'amq.gen-j-pzpTUoJdVrj_MU2__SWw';
$channelName = 'v1.service.aggregate.some-fact';

it('must build from valid json', function () use ($channelName, $replyQueue) {
    $event = OrderMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "'.$channelName.'",
		"replyQueue": "'.$replyQueue.'",
        "correlation_id": "583e3241-0cfe-497c-84ae-2371aef74a7c"
	}
}');
    $this->assertInstanceOf(OrderMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});


it('must throw exception on empty array', function () {
    $this->expectException(MessagePayloadFormatException::class);
    OrderMessagePayload::fromArray([]);
});

it('must throw exception when only array header key is present', function () use ($channelName, $replyQueue) {
    $this->expectException(MessagePayloadFormatException::class);
    OrderMessagePayload::fromArray(['header' => [
        'channel' => $channelName,
        'replyQueue' => $channelName,
        'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c'
    ]]);
});

it('must throw exception when only array data key is present', function () {
    $this->expectException(MessagePayloadFormatException::class);
    OrderMessagePayload::fromArray(['data' => []]);
});


it('must build from valid array', function () use ($channelName, $replyQueue) {
    $event = OrderMessagePayload::fromArray([
        'data' => [],
        'header' => [
            'channel' => $channelName,
            'replyQueue' => $channelName,
            'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
            'status' => 'success'
        ]
    ]);
    $this->assertInstanceOf(OrderMessagePayload::class, $event);
    $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
});
