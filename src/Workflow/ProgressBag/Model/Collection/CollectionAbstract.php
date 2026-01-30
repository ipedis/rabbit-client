<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Contract\CollectionInterface;
use Traversable;

abstract class CollectionAbstract implements CollectionInterface
{
    public function __construct(protected array $items)
    {
    }

    /**
     * @return \ArrayIterator|Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->items[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        if (isset($this->items[$offset])) {
            unset($this->items[$offset]);
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function filter(\Closure $closure): CollectionInterface
    {
        return $this->build(array_filter($this->items, $closure, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @return $this
     */
    protected function build(array $items)
    {
        return new static($items);
    }

    public function map(\Closure $closure): CollectionInterface
    {
        return $this->build(array_map($closure, $this->items));
    }
}
