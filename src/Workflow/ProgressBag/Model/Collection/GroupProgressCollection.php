<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupProgress;

/**
 * @extends CollectionAbstract<GroupProgress>
 */
class GroupProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    /**
     * @return array<string|int, GroupProgress>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
