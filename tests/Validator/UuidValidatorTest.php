<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Validator;

use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Validator\UuidValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidValidatorTest extends TestCase
{
    #[Test]
    public function is_valid_with_valid_uuid(): void
    {
        $uuidValidator = new UuidValidator();
        /** @var string $uuid */
        $uuid = uuid_create();

        $this->assertTrue($uuidValidator->isValid($uuid));
    }

    #[Test]
    #[DataProvider('invalid_uuid_provider')]
    public function is_valid_with_invalid_uuid(string $uuid): void
    {
        $uuidValidator = new UuidValidator();

        $this->assertFalse($uuidValidator->isValid($uuid));
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function invalid_uuid_provider(): \Iterator
    {
        yield 'empty string' => [''];
        yield 'random string' => ['not-a-uuid'];
        yield 'partial uuid' => ['550e8400-e29b-41d4'];
        yield 'uuid with extra chars' => ['550e8400-e29b-41d4-a716-446655440000-extra'];
    }

    #[Test]
    public function validate_with_valid_uuid_does_not_throw(): void
    {
        $uuidValidator = new UuidValidator();
        /** @var string $uuid */
        $uuid = uuid_create();

        $uuidValidator->validate($uuid);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validate_with_invalid_uuid_throws_exception(): void
    {
        $uuidValidator = new UuidValidator();

        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('not-a-uuid is not a valid uuid.');

        $uuidValidator->validate('not-a-uuid');
    }
}
