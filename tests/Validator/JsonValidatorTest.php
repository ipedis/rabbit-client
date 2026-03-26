<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Validator;

use Ipedis\Rabbit\Validator\JsonValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('valid_json_provider')]
    public function is_valid_with_valid_json(string $json): void
    {
        $jsonValidator = new JsonValidator();

        $this->assertTrue($jsonValidator->isValid($json));
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function valid_json_provider(): \Iterator
    {
        yield 'empty object' => ['{}'];
        yield 'empty array' => ['[]'];
        yield 'string' => ['"hello"'];
        yield 'number' => ['42'];
        yield 'null' => ['null'];
        yield 'boolean' => ['true'];
        yield 'nested object' => ['{"key": {"nested": "value"}}'];
        yield 'array of objects' => ['[{"a": 1}, {"b": 2}]'];
    }

    #[Test]
    #[DataProvider('invalid_json_provider')]
    public function is_valid_with_invalid_json(string $json): void
    {
        $jsonValidator = new JsonValidator();

        $this->assertFalse($jsonValidator->isValid($json));
    }

    /**
     * @return \Iterator<string, array{string}>
     */
    public static function invalid_json_provider(): \Iterator
    {
        yield 'empty string' => [''];
        yield 'plain text' => ['not json'];
        yield 'unclosed brace' => ['{'];
        yield 'unclosed bracket' => ['['];
        yield 'trailing comma' => ['{"a": 1,}'];
        yield 'single quotes' => ["{'a': 1}"];
    }
}
