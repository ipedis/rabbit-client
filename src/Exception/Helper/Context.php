<?php

namespace Ipedis\Rabbit\Exception\Helper;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;

class Context implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @var array
     */
    protected array $items;

    /**
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        self::assertContext($items);
        $this->items = $items;
    }

    /**
     * @param array $items
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


    public static function assertContext($data)
    {
        if (is_iterable($data)) {
            foreach ($data as $item) {
                self::assertContext($item);
            }
        } elseif (is_object($data) && !($data instanceof JsonSerializable)) {
            throw new LogicException(
                sprintf('object with type "%s" can\'t be serialize as it is not implementing JsonSerializable',
                    get_class($data))
            );
        }
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * @param mixed $offset
     * @return mixed
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

    /**
     * @return int
     */
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
     * @return bool
     */
    public function has($offset): bool
    {
        return $this->offsetExists($offset);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() < 1;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
