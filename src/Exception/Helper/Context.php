<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;

class Context implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;

    public function __construct(array $items = [])
    {
        self::assertContext($items);
        $this->items = $items;
    }

    /**
     * @return static
     */
    public static function fromArray(array $items): self
    {
        return new static($items);
    }

    public static function initialize(): self
    {
        return new static();
    }


    public static function assertContext($data): void
    {
        if (is_iterable($data)) {
            foreach ($data as $item) {
                self::assertContext($item);
            }
        } elseif (is_object($data) && !($data instanceof JsonSerializable)) {
            throw new LogicException(
                sprintf('object with type "%s" can\'t be serialize as it is not implementing JsonSerializable',
                    $data::class)
            );
        }
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        self::assertContext($value);
        $this->items[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param $offset
     * @param $value
     * @return $this
     */
    public function add($offset, $value): self
    {
        $this->offsetSet($offset, $value);
        return $this;
    }

    /**
     * @param $offset
     * @return mixed|null
     */
    public function get($offset): mixed
    {
        return ($this->offsetExists($offset)) ? $this->offsetGet($offset) : null;
    }

    /**
     * @param $offset
     */
    public function has($offset): bool
    {
        return $this->offsetExists($offset);
    }

    public function isEmpty(): bool
    {
        return $this->count() < 1;
    }

    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
