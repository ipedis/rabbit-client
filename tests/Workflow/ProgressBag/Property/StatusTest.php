<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\ProgressBag\Property;

use Ipedis\Rabbit\Exception\Progress\InvalidStatusException;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    #[Test]
    public function build_pending(): void
    {
        $status = Status::buildPending();

        $this->assertTrue($status->isPending());
        $this->assertFalse($status->isRunning());
        $this->assertFalse($status->isSuccess());
        $this->assertFalse($status->isFailed());
        $this->assertSame('pending', $status->getStatus());
        $this->assertSame('pending', (string) $status);
    }

    #[Test]
    public function build_running(): void
    {
        $status = Status::buildRunning();

        $this->assertFalse($status->isPending());
        $this->assertTrue($status->isRunning());
        $this->assertFalse($status->isSuccess());
        $this->assertFalse($status->isFailed());
    }

    #[Test]
    public function build_success(): void
    {
        $status = Status::buildSuccess();

        $this->assertFalse($status->isPending());
        $this->assertFalse($status->isRunning());
        $this->assertTrue($status->isSuccess());
        $this->assertFalse($status->isFailed());
    }

    #[Test]
    public function build_failed(): void
    {
        $status = Status::buildFailed();

        $this->assertFalse($status->isPending());
        $this->assertFalse($status->isRunning());
        $this->assertFalse($status->isSuccess());
        $this->assertTrue($status->isFailed());
    }

    #[Test]
    #[DataProvider('valid_status_provider')]
    public function build_with_valid_status(string $statusValue): void
    {
        $status = Status::build($statusValue);

        $this->assertSame($statusValue, $status->getStatus());
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function valid_status_provider(): \Iterator
    {
        yield 'pending' => ['pending'];
        yield 'running' => ['running'];
        yield 'success' => ['success'];
        yield 'failed' => ['failed'];
    }

    #[Test]
    public function build_with_invalid_status_throws_exception(): void
    {
        $this->expectException(InvalidStatusException::class);

        Status::build('invalid');
    }

    #[Test]
    public function json_serialize(): void
    {
        $status = Status::buildSuccess();

        $this->assertSame(['status' => 'success'], $status->jsonSerialize());
    }

    #[Test]
    public function to_string(): void
    {
        $this->assertSame('running', (string) Status::buildRunning());
        $this->assertSame('failed', (string) Status::buildFailed());
    }
}
