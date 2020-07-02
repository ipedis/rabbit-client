<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;


class TaskCollection extends CollectionAbstract implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}