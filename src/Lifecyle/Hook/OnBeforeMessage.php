<?php

namespace Ipedis\Rabbit\Lifecyle\Hook;


interface OnBeforeMessage
{
    public function beforeMessageHandled();
}
