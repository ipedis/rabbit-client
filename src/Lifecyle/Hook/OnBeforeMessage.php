<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Lifecyle\Hook;

interface OnBeforeMessage
{
    public function beforeMessageHandled();
}
