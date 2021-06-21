<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Contract\CollectionInterface;
use Traversable;

abstract class CollectionAbstract implements CollectionInterface
{
    /**
     * @var array
     */
    protected array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
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
     * @return bool
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
    public function offsetSet($offset, $value)
    {
        $this->items[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        if (isset($this->items[$offset])) {
            unset($this->items[$offset]);
        }
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @param \Closure $closure
     * @return CollectionInterface
     */
    public function filter(\Closure $closure): CollectionInterface
    {
        return $this->build(array_filter($this->items, $closure, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param \Closure $closure
     * @return CollectionInterface
     */
    public function map(\Closure $closure): CollectionInterface
    {
        return $this->build(array_map($closure, $this->items));
    }

    /**
     * @param array $items
     * @return $this
     */
    protected function build(array $items)
    {
        return new static($items);
    }
}
