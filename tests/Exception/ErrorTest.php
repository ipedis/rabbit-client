<?php

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testFromArray()
    {
        $this->assertInstanceOf(
            Error::class,
            $this->makeError()
        );
    }

    public function testJsonSerialize()
    {
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'message' => 'foo message',
                'code' => 0,
                'context' => [['this', 'context']]
            ]),
            json_encode($this->makeError())
        );

        $this->expectException(MessagePayloadFormatException::class);
        $this->makeNotSerializableError();
    }

    public function testGetMessage()
    {
        $this->assertEquals('foo message', $this->makeError()->getMessage());
    }

    public function testHasContext()
    {
        $this->assertEquals(true, $this->makeError()->hasContext());
    }

    public function testGetCode()
    {
        $error = $this->makeError();
        $this->assertIsNumeric($error->getCode());
        $this->assertEquals(0, $error->getCode());
    }

    public function testGetContext()
    {
        $context = $this->makeError()->getContext();
        $this->assertIsArray($context);
        $this->assertContains('context', $context[0]);
    }

    protected function makeError(): Error
    {
        return Error::fromArray([
            'exception' => ['message' => 'foo message', 'code' => 0],
            'context' => [['this', 'context']]
        ]);
    }

    protected function makeNotSerializableError(): Error
    {
        return Error::fromArray([
            'context' => [['deep' => ['tree' => new self()]]]
        ]);
    }
}
