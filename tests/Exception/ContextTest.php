<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Context;
use PHPUnit\Framework\TestCase;
use LogicException;

final class ContextTest extends TestCase
{
    public function testAssertContext(): void
    {
        $this->expectException(LogicException::class);
        Context::assertContext(new self('test'));

        $this->expectException(LogicException::class);
        Context::assertContext(['very' => ['deep' => ['information' => new self('test')]]]);

        $this->assertNull(Context::assertContext(['very' => ['deep' => ['information' => new self('test')]]]));
    }

    public function testAdd(): void
    {
        $context = $this->makeContext();
        $this->assertCount(2, $context);
        // add new context.
        $context->add('another', 'data');
        $this->assertCount(3, $context);
        // also try to override data
        $context->add('another', 'databis');
        $this->assertCount(3, $context);
    }


    public function testJsonSerialize(): void
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

    public function testGet(): void
    {
        $this->assertEquals('flat', $this->makeContext()->get('bar'));
        $this->assertNull($this->makeContext()->get('notdefined'));
    }

    public function testHas(): void
    {
        $this->assertTrue($this->makeContext()->has('foo'));
        $this->assertTrue($this->makeContext()->has('bar'));
        $this->assertFalse($this->makeContext()->has('notdefined'));
    }

    public function testFromArray(): void
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

    public function testInitialize(): void
    {
        $this->assertInstanceOf(
            Context::class,
            Context::initialize()
        );
    }

    public function testIsEmpty(): void
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
