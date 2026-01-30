<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Lifecyle\Hook;

interface OnAfterMessage
{
    public function afterMessageHandled();
}
