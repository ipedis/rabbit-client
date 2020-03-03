<?php


namespace Ipedis\Demo\Rabbit\Worker\Handler;


use Ipedis\Rabbit\Consumer\Handler\MessageHandler;
use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;

class ManagerHandler extends MessageHandler
{
    /**
     * @var int
     */
    protected $numberTask;

    public function __construct()
    {
        $this->numberTask = 10;
    }

    public function getNumberTask(): int
    {
        return $this->numberTask;
    }

    public function onProgress(ReplyToMessagePayload $messagePayload)
    {
        print_r("\t progress :| - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onSuccess(ReplyToMessagePayload $messagePayload)
    {
        print_r("\t success :) - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onError(ReplyToMessagePayload $messagePayload)
    {
        print_r("\t fail :( - ".json_encode($messagePayload->getData())."\n\n\n");
    }

    public function onFinish(ReplyToMessagePayload $messagePayload)
    {
        print_r("\t Finish :( - ".json_encode($messagePayload->getData())."\n\n\n");
    }
}
