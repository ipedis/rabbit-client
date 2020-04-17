<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag;


interface ProgressBagInterface
{
    const STATUS_PENDING  = 'pending';
    const STATUS_RUNNING  = 'running';
    const STATUS_FINISHED = 'finished';
}
