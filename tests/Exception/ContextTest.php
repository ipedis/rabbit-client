<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Exception;

use Ipedis\Rabbit\Exception\Helper\Context;
use PHPUnit\Framework\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;

final class ContextTest extends TestCase
{
    /**
     */
    #[Test]
    public function assert_context(): void
    {
        $this->expectException(LogicException::class);
        Context::assertContext(new self('test'));

        $this->expectException(LogicException::class);
        Context::assertContext(['very' => ['deep' => ['information' => new self('test')]]]);

        $this->assertNull(Context::assertContext(['very' => ['deep' => ['information' => new self('test')]]]));
    }

    /**
     */
    #[Test]
    public function add(): void
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


    /**
     */
    #[Test]
    public function json_serialize(): void
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

    /**
     */
    #[Test]
    public function get(): void
    {
        $this->assertEquals('flat', $this->makeContext()->get('bar'));
        $this->assertNull($this->makeContext()->get('notdefined'));
    }

    /**
     */
    #[Test]
    public function has(): void
    {
        $this->assertTrue($this->makeContext()->has('foo'));
        $this->assertTrue($this->makeContext()->has('bar'));
        $this->assertFalse($this->makeContext()->has('notdefined'));
    }

    /**
     */
    #[Test]
    public function from_array(): void
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

    /**
     */
    #[Test]
    public function initialize(): void
    {
        $this->assertInstanceOf(
            Context::class,
            Context::initialize()
        );
    }

    /**
     */
    #[Test]
    public function is_empty(): void
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
