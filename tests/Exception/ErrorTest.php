<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Context;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ErrorTest extends TestCase
{
    /**
     */
    #[Test]
    public function from_array(): void
    {
        $this->assertInstanceOf(
            Error::class,
            $this->makeError()
        );
    }

    /**
     */
    #[Test]
    public function json_serialize(): void
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

    /**
     */
    #[Test]
    public function get_message(): void
    {
        $this->assertSame('foo message', $this->makeError()->getMessage());
    }

    /**
     */
    #[Test]
    public function has_context(): void
    {
        $this->assertEquals(true, $this->makeError()->hasContext());
    }

    /**
     */
    #[Test]
    public function get_code(): void
    {
        $error = $this->makeError();
        $this->assertIsNumeric($error->getCode());
        $this->assertSame(0, $error->getCode());
    }

    /**
     */
    #[Test]
    public function get_context(): void
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
