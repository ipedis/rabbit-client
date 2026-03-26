<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupedTasksProgress;

/**
 * @extends CollectionAbstract<GroupedTasksProgress>
 */
class GroupedTasksProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    /**
     * @return array<string|int, GroupedTasksProgress>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
