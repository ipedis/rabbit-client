<?php


namespace Ipedis\Rabbit\Consumer\Handler;


use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;

interface MessageHandlerInterface
{
    const TYPE_PROGRESS = 'progress';
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const AVAILABLE_TYPES = [self::TYPE_ERROR, self::TYPE_PROGRESS, self::TYPE_SUCCESS];
    const STATUS_KEY = 'status';

    public function on(AMQPMessage $req);
    public function onSuccess(ReplyToMessagePayload $messagePayload);
    public function onError(ReplyToMessagePayload $messagePayload);
    public function onProgress(ReplyToMessagePayload $messagePayload);
    public function onFinish(ReplyToMessagePayload $messagePayload);
}
