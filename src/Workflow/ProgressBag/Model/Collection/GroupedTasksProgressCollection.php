<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

class GroupedTasksProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
