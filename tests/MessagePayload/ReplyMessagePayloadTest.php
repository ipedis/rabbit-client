<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReplyMessagePayloadTest extends TestCase
{
    private string $channelName = 'amq.gen-j-pzpTUoJdVrj_MU2__SWw';

    #[Test]
    public function it_must_build_from_valid_json(): void
    {
        $event = ReplyMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "' . $this->channelName . '",
        "correlation_id": "583e3241-0cfe-497c-84ae-2371aef74a7c",
        "status": "not_valid_one"
	}
}');
        $this->assertInstanceOf(ReplyMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    #[Test]
    public function it_must_throw_exception_on_empty_array(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        ReplyMessagePayload::fromArray([]);
    }

    #[Test]
    public function it_must_throw_exception_when_only_array_header_key_is_present(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        ReplyMessagePayload::fromArray(['header' => [
            'channel' => $this->channelName,
            'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
            'status' => 'success',
        ]]);
    }

    #[Test]
    public function it_must_throw_exception_when_only_array_data_key_is_present(): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        ReplyMessagePayload::fromArray(['data' => []]);
    }

    #[Test]
    public function it_must_throw_exception_when_status_is_not_valid(): void
    {
        $event = ReplyMessagePayload::fromArray([
            'data' => [],
            'header' => [
                'channel' => $this->channelName,
                'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
                'status' => 'not_valid_one',
            ],
        ]);
        $this->assertInstanceOf(ReplyMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    #[Test]
    public function it_must_build_from_valid_array(): void
    {
        $event = ReplyMessagePayload::fromArray([
            'data' => [],
            'header' => [
                'channel' => $this->channelName,
                'correlation_id' => '583e3241-0cfe-497c-84ae-2371aef74a7c',
                'status' => 'success',
            ],
        ]);
        $this->assertInstanceOf(ReplyMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }
}
