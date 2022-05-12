<?php

namespace Ipedis\Test\Rabbit\Exception;

use Exception;
use Ipedis\Rabbit\Exception\Helper\Context;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayloadInterface;
use JsonSerializable;
use LogicException;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    public function testFromExceptionTyping()
    {
        // check if Serializer have a right type..
        $this->assertInstanceOf(
            Serializer::class,
            Serializer::fromException(new Exception('foo message'))
        );
        // check if we can use fromException factory.
        $context = ['this', 'context'];
        $serializer = Serializer::fromException(new Exception('foo message'), Context::fromArray($context));
        $this->assertInstanceOf(
            Serializer::class,
            $serializer
        );
        // check if context getter return the right type.
        $contextBag = $serializer->getContext();
        $this->assertInstanceOf(
            Context::class,
            $contextBag
        );
        // check if the bag contain our original context.
        $this->assertEquals($context[0], $contextBag->get(0));
        $this->assertEquals($context[1], $contextBag->get(1));
    }

    public function testAddContext()
    {
        $context1 = ['foo' => 'bar'];
        $context2 = ['another' => 'one'];
        $context3 = 'foo';

        $serializer = Serializer::fromException(new Exception('foo message'));
        $serializer
            ->addContext('context1', $context1)
            ->addContext('context2', $context2)
            ->addContext('bar', $context3)
        ;

        $this->assertContains($context1, $serializer->getContext());
        $this->assertContains($context2, $serializer->getContext());
        $this->assertContains($context3, $serializer->getContext());

        $this->expectException(LogicException::class);
        $serializer->addContext('deep', ['deep' => ['tree' => new self()]]);
    }

    public function testJsonSerialize()
    {
        $serializer = Serializer::fromException(new Exception('foo message'), Context::fromArray(['this', 'context']));
        $json = json_encode($serializer);
        $this->assertJson($json);
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'exception' => ['message' => 'foo message', 'code' => 0, 'className' => 'Exception'],
                'context' => ['this', 'context']
            ]),
            $json
        );
    }

    public function testFromMessage()
    {
        $error = Serializer::fromMessage(new class() implements JsonSerializable, MessagePayloadInterface, ReplyMessagePayloadInterface {
            public function getHeaders(): array
            {
                return [];
            }

            public function getData(): array
            {
                return [];
            }

            public function getChannel(): string
            {
                return '';
            }

            public function getStringifyData(): string
            {
                return '';
            }

            public function jsonSerialize(): mixed
            {
                return '';
            }

            public function getReply()
            {
                return ['error' => [
                    'exception' => ['message' => 'foo message', 'code' => 0],
                    'context' => [['this', 'context']]
                ]];
            }

            public function hasReply(): bool
            {
                return true;
            }
        });

        $this->assertInstanceOf(Error::class, $error);
    }
}
