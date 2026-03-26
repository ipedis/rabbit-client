<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\ProgressBag\Property;

use DateTime;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimerTest extends TestCase
{
    #[Test]
    public function build_with_zero_spent(): void
    {
        $timer = Timer::build(0.0);

        $this->assertEqualsWithDelta(0.0, $timer->getSpent(), PHP_FLOAT_EPSILON);
        $this->assertNotInstanceOf(DateTime::class, $timer->getStartedAt());
        $this->assertNotInstanceOf(DateTime::class, $timer->getFinishedAt());
    }

    #[Test]
    public function build_with_times(): void
    {
        $start = new DateTime('2024-01-01 10:00:00');
        $end = new DateTime('2024-01-01 10:05:00');

        $timer = Timer::build(300.0, $start, $end);

        $this->assertEqualsWithDelta(300.0, $timer->getSpent(), PHP_FLOAT_EPSILON);
        $this->assertSame($start, $timer->getStartedAt());
        $this->assertSame($end, $timer->getFinishedAt());
    }

    #[Test]
    public function build_with_negative_spent_throws(): void
    {
        $this->expectException(InvalidSpentTimeException::class);

        Timer::build(-1.0);
    }

    #[Test]
    public function build_with_start_after_end_throws(): void
    {
        $start = new DateTime('2024-01-01 11:00:00');
        $end = new DateTime('2024-01-01 10:00:00');

        $this->expectException(InvalidTimeException::class);

        Timer::build(0.0, $start, $end);
    }

    #[Test]
    public function build_with_only_start_time(): void
    {
        $start = new DateTime('2024-01-01 10:00:00');
        $timer = Timer::build(0.0, $start);

        $this->assertSame($start, $timer->getStartedAt());
        $this->assertNotInstanceOf(DateTime::class, $timer->getFinishedAt());
    }

    #[Test]
    public function build_with_same_start_and_end(): void
    {
        $time = new DateTime('2024-01-01 10:00:00');
        $timer = Timer::build(0.0, $time, $time);

        $this->assertSame($time, $timer->getStartedAt());
        $this->assertSame($time, $timer->getFinishedAt());
    }

    #[Test]
    public function json_serialize_without_times(): void
    {
        $timer = Timer::build(1.5);

        $json = $timer->jsonSerialize();

        $this->assertEqualsWithDelta(1.5, $json['spent'], PHP_FLOAT_EPSILON);
        $this->assertSame('1500 ms', $json['spentTime']);
        $this->assertNull($json['startedAt']);
        $this->assertNull($json['finishedAt']);
    }

    #[Test]
    public function json_serialize_with_times(): void
    {
        $start = new DateTime('2024-01-01 10:00:00.123456');
        $end = new DateTime('2024-01-01 10:00:01.654321');

        $timer = Timer::build(1.530865, $start, $end);

        $json = $timer->jsonSerialize();

        $this->assertEqualsWithDelta(1.530865, $json['spent'], PHP_FLOAT_EPSILON);
        $this->assertNotNull($json['startedAt']);
        $this->assertNotNull($json['finishedAt']);
    }
}
