<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\MessagePayloadAbstract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventPayloadAbstractTest extends TestCase
{
    private string $channelName = 'v1.dummy.some.channel';

    #[Test]
    public function it_must_build_dto(): void
    {
        $event = EventMessagePayload::build($this->channelName);
        $this->assertInstanceOf(EventMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function invalidJsonProvider(): \Iterator
    {
        yield 'not even a json' => ['not even a json'];
        yield 'empty json' => ['{}'];
        yield 'only header' => ['{"header": {"channel": "something"}}'];
        yield 'only data' => ['{"data": ""}'];
    }

    #[Test]
    #[DataProvider('invalidJsonProvider')]
    public function it_must_throw_exception_on_invalid_json(string $value): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        EventMessagePayload::fromJson($value);
    }

    #[Test]
    public function it_must_build_from_valid_json(): void
    {
        $event = EventMessagePayload::fromJson('{
	"data": [],
	"header": {
		"channel": "something"
	}
}');
        $this->assertInstanceOf(EventMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    /**
     * @return \Iterator<string, array{array<mixed>}>
     */
    public static function invalidArrayProvider(): \Iterator
    {
        yield 'empty payload' => [[]];
        yield 'only header' => [['header' => []]];
        yield 'only data' => [['data' => []]];
    }

    #[Test]
    #[DataProvider('invalidArrayProvider')]
    public function it_must_throw_exception_on_empty_array(array $payload): void
    {
        $this->expectException(MessagePayloadFormatException::class);
        EventMessagePayload::fromArray($payload);
    }

    #[Test]
    public function it_must_build_from_valid_array(): void
    {
        $event = EventMessagePayload::fromArray([
            'data' => [],
            'header' => [
                'channel' => 'something',
            ],
        ]);
        $this->assertInstanceOf(EventMessagePayload::class, $event);
        $this->assertInstanceOf(MessagePayloadAbstract::class, $event);
    }

    #[Test]
    public function it_must_contain_channel_header_by_default(): void
    {
        $event = EventMessagePayload::build($this->channelName);
        $this->assertSame($event->getChannel(), $this->channelName);
    }

    #[Test]
    public function it_must_not_been_parasitized_by_header_parameter(): void
    {
        $event = EventMessagePayload::build($this->channelName, [], [
            MessagePayloadAbstract::HEADER_CHANNEL => 'something.else',
        ]);
        $this->assertSame($event->getChannel(), $this->channelName);
    }

    #[Test]
    public function it_contain_uuid_header_by_default(): void
    {
        $event = EventMessagePayload::build($this->channelName);
        $this->assertIsString($event->getUuid());
        $this->assertTrue(uuid_is_valid($event->getUuid()));
    }

    #[Test]
    public function it_uuid_can_be_defined_on_header_parameter(): void
    {
        $uuid = uuid_create();
        $event = EventMessagePayload::build($this->channelName, [], [
            MessagePayloadAbstract::HEADER_UUID => $uuid,
        ]);
        $this->assertEquals($uuid, $event->getUuid());
    }

    #[Test]
    public function it_contain_send_at_timezone_by_default(): void
    {
        $event = EventMessagePayload::build($this->channelName);
        $this->assertIsNumeric($event->getTimestamp());
    }

    #[Test]
    public function it_should_return_data_encoded_in_json(): void
    {
        $event = EventMessagePayload::build($this->channelName, ['some' => 'data']);
        $this->assertJsonStringEqualsJsonString(json_encode(['some' => 'data']), $event->getStringifyData());
    }

    #[Test]
    public function it_should_return_valid_timezone_name(): void
    {
        $event = EventMessagePayload::build($this->channelName, ['some' => 'data']);
        $this->assertSame('UTC', $event->getTimezoneName());
    }
}
