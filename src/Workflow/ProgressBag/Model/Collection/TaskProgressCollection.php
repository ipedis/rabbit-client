<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

class TaskProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
