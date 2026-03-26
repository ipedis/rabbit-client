<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderMessagePayloadTest extends TestCase
{
    private string $replyQueue = 'amq.gen-j-pzpTUoJdVrj_MU2__SWw';

    private string $channelName = 'v1.service.aggregate.some-fact';

    #[Test]
    public function it_must_build_from_valid_json(): void
    {
        $event = OrderMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "' . $this->channelName . '",
		"replyQueue": "' . $this->replyQueue . '",
        "correlation_id": "583e3241-0cfe-497c-84ae-2371aef74a7c"
	}
}');
        $this->assertInstanceOf(OrderMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    #[Test]
    public function it_must_throw_exception_on_empty_array(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        OrderMessagePayload::fromArray([]);
    }

    #[Test]
    public function it_must_throw_exception_when_only_array_header_key_is_present(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        OrderMessagePayload::fromArray(['header' => [
            'channel' => $this->channelName,
            'replyQueue' => $this->channelName,
            'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
        ]]);
    }

    #[Test]
    public function it_must_throw_exception_when_only_array_data_key_is_present(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        OrderMessagePayload::fromArray(['data' => []]);
    }

    #[Test]
    public function it_must_build_from_valid_array(): void
    {
        $event = OrderMessagePayload::fromArray([
            'data' => [],
            'header' => [
                'channel' => $this->channelName,
                'replyQueue' => $this->channelName,
                'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
                'status' => 'success',
            ],
        ]);
        $this->assertInstanceOf(OrderMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }
}
