<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

class GroupCollection extends CollectionAbstract implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}