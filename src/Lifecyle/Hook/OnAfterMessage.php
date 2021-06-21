<?php

namespace Ipedis\Rabbit\Lifecyle\Hook;

interface OnAfterMessage
{
    public function afterMessageHandled();
}
