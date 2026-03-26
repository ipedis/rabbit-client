<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Workflow\ProgressBag\Property;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PercentageTest extends TestCase
{
    #[Test]
    public function build_with_valid_values(): void
    {
        $percentage = Percentage::build(75.0, 50.0, 25.0);

        $this->assertEqualsWithDelta(75.0, $percentage->getCompleted(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(50.0, $percentage->getSuccess(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(25.0, $percentage->getFailed(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function init_creates_zero_percentage(): void
    {
        $percentage = Percentage::init();

        $this->assertEqualsWithDelta(0.0, $percentage->getCompleted(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $percentage->getSuccess(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $percentage->getFailed(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function build_100_percent_success(): void
    {
        $percentage = Percentage::build(100.0, 100.0, 0.0);

        $this->assertEqualsWithDelta(100.0, $percentage->getCompleted(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(100.0, $percentage->getSuccess(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $percentage->getFailed(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function build_100_percent_failed(): void
    {
        $percentage = Percentage::build(100.0, 0.0, 100.0);

        $this->assertEqualsWithDelta(100.0, $percentage->getCompleted(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $percentage->getSuccess(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(100.0, $percentage->getFailed(), PHP_FLOAT_EPSILON);
    }

    #[Test]
    public function build_with_completed_not_matching_sum_throws(): void
    {
        $this->expectException(InvalidProgressValueException::class);

        Percentage::build(50.0, 30.0, 30.0);
    }

    #[Test]
    public function build_with_negative_completed_throws(): void
    {
        $this->expectException(InvalidProgressValueException::class);

        Percentage::build(-10.0, -5.0, -5.0);
    }

    #[Test]
    public function build_with_over_100_throws(): void
    {
        $this->expectException(InvalidProgressValueException::class);

        Percentage::build(150.0, 100.0, 50.0);
    }

    #[Test]
    public function build_with_negative_success_throws(): void
    {
        $this->expectException(InvalidProgressValueException::class);

        Percentage::build(0.0, -10.0, 10.0);
    }

    #[Test]
    public function build_with_negative_failed_throws(): void
    {
        $this->expectException(InvalidProgressValueException::class);

        Percentage::build(0.0, 10.0, -10.0);
    }

    #[Test]
    public function calculate_returns_percentage(): void
    {
        $this->assertEqualsWithDelta(50.0, Percentage::calculate(5, 10), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(100.0, Percentage::calculate(10, 10), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, Percentage::calculate(0, 10), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(33.33, Percentage::calculate(1, 3), 0.01);
    }

    #[Test]
    public function json_serialize(): void
    {
        $percentage = Percentage::build(60.0, 40.0, 20.0);

        $this->assertSame([
            'completed' => 60.0,
            'success' => 40.0,
            'failed' => 20.0,
        ], $percentage->jsonSerialize());
    }
}
