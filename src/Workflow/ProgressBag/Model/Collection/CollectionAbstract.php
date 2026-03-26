<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Contract\CollectionInterface;

/**
 * @template T
 * @implements CollectionInterface<T>
 */
abstract class CollectionAbstract implements CollectionInterface
{
    /**
     * @param array<string|int, T> $items
     */
    public function __construct(protected array $items)
    {
    }

    /**
     * @return \ArrayIterator<string|int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->items[$offset])) {
            unset($this->items[$offset]);
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<string|int, T>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    public function filter(\Closure $closure): static
    {
        return new static(array_filter($this->items, $closure, ARRAY_FILTER_USE_BOTH)); // @phpstan-ignore return.type, new.static
    }

    public function map(\Closure $closure): static
    {
        return new static(array_map($closure, $this->items)); // @phpstan-ignore return.type, new.static
    }
}
