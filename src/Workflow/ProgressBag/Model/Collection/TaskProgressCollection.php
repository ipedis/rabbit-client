<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Model\Collection;

use Ipedis\Rabbit\Workflow\ProgressBag\Model\TaskProgress;

/**
 * @extends CollectionAbstract<TaskProgress>
 */
class TaskProgressCollection extends CollectionAbstract implements \JsonSerializable
{
    /**
     * @return array<string|int, TaskProgress>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
