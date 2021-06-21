<?php

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use JsonSerializable;
use LogicException;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    public function testFromExceptionTyping()
    {
        $this->assertInstanceOf(
            Serializer::class,
            Serializer::fromException(new \Exception('foo message'))
        );

        $context = ['this', 'context'];
        $serializer = Serializer::fromException(new \Exception('foo message'), $context);
        $this->assertInstanceOf(
            Serializer::class,
            $serializer
        );
        $this->assertContains($context, $serializer->getContext());
    }

    public function testAddContext()
    {
        $context1 = ['foo' => 'bar'];
        $context2 = ['another' => 'one'];
        $context3 = 'foo';

        $serializer = Serializer::fromException(new \Exception('foo message'));
        $serializer
            ->addContext($context1)
            ->addContext($context2)
            ->addContext($context3)
        ;

        $this->assertContains($context1, $serializer->getContext());
        $this->assertContains($context2, $serializer->getContext());
        $this->assertContains($context3, $serializer->getContext());

        $this->expectException(LogicException::class);
        $serializer->addContext([['deep' => ['tree' => new self()]]]);
    }

    public function testJsonSerialize()
    {
        $serializer = Serializer::fromException(new \Exception('foo message'), ['this', 'context']);
        $json = json_encode($serializer);
        $this->assertJson($json);
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'exception' => ['message' => 'foo message', 'code' => 0],
                'context' => [['this', 'context']]
            ]),
            $json
        );
    }

    public function testFromMessage()
    {
        $error = Serializer::fromMessage(new class() implements JsonSerializable, MessagePayloadInterface {
            public function getHeaders(): array
            {
                return [];
            }

            public function getData(): array
            {
                return ['error' => [
                    'exception' => ['message' => 'foo message', 'code' => 0],
                    'context' => [['this', 'context']]
                ]];
            }

            public function getChannel(): string
            {
                return '';
            }

            public function getStringifyData(): string
            {
                return '';
            }

            public function jsonSerialize()
            {
                return '';
            }
        });

        $this->assertInstanceOf(Error::class, $error);
    }
}
