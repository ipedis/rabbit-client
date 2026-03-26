<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Contract;

/**
 * @template T
 * @extends \ArrayAccess<string|int, T>
 * @extends \IteratorAggregate<string|int, T>
 */
interface CollectionInterface extends \ArrayAccess, \IteratorAggregate, \Countable
{
    public function filter(\Closure $closure): static;

    public function map(\Closure $closure): static;
}
