<?php


namespace Ipedis\Rabbit\Workflow\ProgressBag\Contract;


interface CollectionInterface extends \ArrayAccess, \IteratorAggregate, \Countable
{
    public function filter(\Closure $closure): CollectionInterface;

    public function map(\Closure $closure): CollectionInterface;
}