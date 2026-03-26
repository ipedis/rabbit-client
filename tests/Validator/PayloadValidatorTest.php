<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Validator;

use Ipedis\Rabbit\Validator\PayloadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PayloadValidatorTest extends TestCase
{
    private static string $schema = '{
  "type":"object",
  "properties": {
    "hasToFail": {
      "type":"boolean"
    },
    "name":{
      "type":"string"
    }
  },
  "required": ["hasToFail", "name"],
  "additionalProperties":false
}';

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function validPayloadProvider(): \Iterator
    {
        yield 'valid with true' => [self::$schema, '{ "hasToFail": true, "name": "foo" }'];
        yield 'valid with false' => [self::$schema, '{ "hasToFail": false, "name": "" }'];
        yield 'valid reordered' => [self::$schema, '{ "name": "foo", "hasToFail": true }'];
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function invalidPayloadProvider(): \Iterator
    {
        yield 'wrong type' => [self::$schema, '{ "hasToFail": -1, "name": "foo" }'];
        yield 'missing required' => [self::$schema, '{ "name": "" }'];
        yield 'invalid json' => [self::$schema, '"name": "foo", "hasToFail": true }'];
        yield 'additional properties' => [self::$schema, '{"name": "foo","extra": "ff", "hasToFail": true }'];
    }

    /**
     * @return \Iterator<string, array{string, string, string}>
     */
    public static function errorMessageProvider(): \Iterator
    {
        yield 'wrong type error' => [self::$schema, '{ "hasToFail": -1, "name": "foo" }', 'hasToFail'];
        yield 'missing required error' => [self::$schema, '{ "name": "" }', 'hasToFail'];
        yield 'additional properties error' => [self::$schema, '{"name": "foo","extra": "ff", "hasToFail": true }', 'extra'];
    }

    #[Test]
    #[DataProvider('validPayloadProvider')]
    public function it_should_detect_valid_payload_structure(string $schema, string $payload): void
    {
        $this->assertTrue((new PayloadValidator())->validate($payload, $schema));
    }

    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_should_detect_invalid_payload_structure(string $schema, string $payload): void
    {
        $this->assertFalse((new PayloadValidator())->validate($payload, $schema));
    }

    #[Test]
    #[DataProvider('errorMessageProvider')]
    public function it_should_return_readable_message_when_error_is_detected(string $schema, string $payload, string $message): void
    {
        $payloadValidator = new PayloadValidator();
        $payloadValidator->validate($payload, $schema);

        $error = $payloadValidator->getErrorAsString();
        $this->assertStringContainsString($message, $error);
    }
}
