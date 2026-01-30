<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Context;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public function testFromArray(): void
    {
        $this->assertInstanceOf(
            Error::class,
            $this->makeError()
        );
    }

    public function testJsonSerialize(): void
    {
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'message' => 'foo message',
                'code' => 0,
                'context' => ['this', 'context' => 'information']
            ]),
            json_encode($this->makeError())
        );

        $this->expectException(MessagePayloadFormatException::class);
        $this->makeNotSerializableError();
    }

    public function testGetMessage(): void
    {
        $this->assertSame('foo message', $this->makeError()->getMessage());
    }

    public function testHasContext(): void
    {
        $this->assertEquals(true, $this->makeError()->hasContext());
    }

    public function testGetCode(): void
    {
        $error = $this->makeError();
        $this->assertIsNumeric($error->getCode());
        $this->assertSame(0, $error->getCode());
    }

    public function testGetContext(): void
    {
        $context = $this->makeError()->getContext();
        $this->assertInstanceOf(
            Context::class,
            $context
        );
        $this->assertEquals(true, $context->has('context'));
    }

    protected function makeError(): Error
    {
        return Error::fromArray([
            'exception' => ['message' => 'foo message', 'code' => 0],
            'context' => ['this', 'context' => 'information']
        ]);
    }

    protected function makeNotSerializableError(): Error
    {
        return Error::fromArray([
            'context' => [['deep' => ['tree' => new self('test')]]]
        ]);
    }
}
