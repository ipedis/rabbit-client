<?php

namespace Ipedis\Demo\Rabbit\Worker\Handler;

use Ipedis\Rabbit\Consumer\Handler\MessageHandler;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

class ManagerHandler extends MessageHandler
{
    /**
     * @var int
     */
    protected int $numberTask;

    public function __construct(int $numberTask)
    {
        $this->numberTask = $numberTask;
    }

    public function getNumberTask(): int
    {
        return $this->numberTask;
    }

    public function onStarting(ReplyMessagePayload $messagePayload)
    {
//        print_r("\t starting :) - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onProgress(ReplyMessagePayload $messagePayload)
    {
//        print_r("\t progress :| - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onSuccess(ReplyMessagePayload $messagePayload)
    {
        //print_r("\t success :) - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onError(ReplyMessagePayload $messagePayload, Error $error)
    {
        //print_r("\t fail :( - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onFinish(ReplyMessagePayload $messagePayload)
    {
        //print_r("\t Finish :( - ".json_encode($messagePayload->getData())."\n\n\n");
    }
}
