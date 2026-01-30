<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Contract;

use Ipedis\Rabbit\Workflow\ProgressBag\Property\Percentage;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;

interface ProgressBagInterface
{
    public function isPending(): bool;

    public function isRunning(): bool;

    public function isCompleted(): bool;

    public function hasFailure(): bool;

    public function getStatus(): Status;

    public function getExecutionTime(): float;

    public function getPercentage(): Percentage;
}
