<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;

/**
 * @implements ArrayAccess<string|int, mixed>
 * @implements IteratorAggregate<string|int, mixed>
 */
final class Context implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<string|int, mixed> */
    private array $items;

    /**
     * @param array<string|int, mixed> $items
     */
    public function __construct(array $items = [])
    {
        self::assertContext($items);
        $this->items = $items;
    }

    /**
     * @param array<string|int, mixed> $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public static function initialize(): self
    {
        return new self();
    }


    public static function assertContext(mixed $data): void
    {
        if (is_iterable($data)) {
            foreach ($data as $item) {
                self::assertContext($item);
            }
        } elseif (is_object($data) && !($data instanceof JsonSerializable)) {
            throw new LogicException(
                sprintf(
                    'object with type "%s" can\'t be serialize as it is not implementing JsonSerializable',
                    $data::class
                )
            );
        }
    }

    /**
     * @return ArrayIterator<string|int, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        assert(is_string($offset) || is_int($offset));

        return array_key_exists($offset, $this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset): mixed
    {
        assert(is_string($offset) || is_int($offset));

        return $this->items[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        assert(is_string($offset) || is_int($offset));
        self::assertContext($value);
        $this->items[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        assert(is_string($offset) || is_int($offset));
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return $this
     */
    public function add(mixed $offset, mixed $value): self
    {
        $this->offsetSet($offset, $value);
        return $this;
    }

    public function get(mixed $offset): mixed
    {
        return ($this->offsetExists($offset)) ? $this->offsetGet($offset) : null;
    }

    public function has(mixed $offset): bool
    {
        return $this->offsetExists($offset);
    }

    public function isEmpty(): bool
    {
        return $this->count() < 1;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
