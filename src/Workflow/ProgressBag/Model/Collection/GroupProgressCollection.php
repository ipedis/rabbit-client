<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

class GroupProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
