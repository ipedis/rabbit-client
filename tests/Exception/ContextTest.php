<?php

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Context;
use PHPUnit\Framework\TestCase;
use LogicException;

class ContextTest extends TestCase
{

    public function testAssertContext()
    {
        $this->expectException(LogicException::class);
        Context::assertContext(new self());

        $this->expectException(LogicException::class);
        Context::assertContext(['very' => ['deep' => ['information' => new self()]]]);

        $this->assertNull(Context::assertContext(['very' => ['deep' => ['information' => new self()]]]));
    }

    public function testAdd()
    {
        $context = $this->makeContext();
        $this->assertEquals(2, count($context));
        // add new context.
        $context->add('another', 'data');
        $this->assertEquals(3, count($context));
        // also try to override data
        $context->add('another', 'databis');
        $this->assertEquals(3, count($context));
    }


    public function testJsonSerialize()
    {
        $this->assertJsonStringEqualsJsonString(
            '[]',
            json_encode(Context::initialize())
        );

        $this->assertJsonStringEqualsJsonString(
            json_encode(['foo' => ['bar' => 'deep'], 'bar' => 'flat']),
            json_encode($this->makeContext())
        );
    }

    public function testGet()
    {
        $this->assertEquals('flat', $this->makeContext()->get('bar'));
        $this->assertNull($this->makeContext()->get('notdefined'));
    }

    public function testHas()
    {
        $this->assertTrue($this->makeContext()->has('foo'));
        $this->assertTrue($this->makeContext()->has('bar'));
        $this->assertFalse($this->makeContext()->has('notdefined'));
    }

    public function testFromArray()
    {
        $this->assertInstanceOf(
            Context::class,
            Context::fromArray([])
        );

        $this->assertInstanceOf(
            Context::class,
            $this->makeContext()
        );
    }

    public function testInitialize()
    {
        $this->assertInstanceOf(
            Context::class,
            Context::initialize()
        );
    }

    public function testIsEmpty()
    {
        $this->assertNotTrue($this->makeContext()->isEmpty());
        $this->assertTrue(Context::initialize()->isEmpty());
        $this->assertTrue(Context::fromArray([])->isEmpty());
    }

    protected function makeContext(): Context
    {
        return Context::fromArray(['foo' => ['bar' => 'deep'], 'bar' => 'flat']);
    }
}
