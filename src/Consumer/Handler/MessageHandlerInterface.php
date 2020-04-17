<?php

namespace Ipedis\Rabbit\Consumer\Handler;


use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

interface MessageHandlerInterface
{
    const TYPE_PLANIFIED    = 'planified';
    const TYPE_DISPATCHED   = 'dispatched';
    const TYPE_PROGRESS     = 'progress';
    const TYPE_SUCCESS      = 'success';
    const TYPE_ERROR        = 'error';
    const AVAILABLE_TYPES   = [self::TYPE_PLANIFIED, self::TYPE_DISPATCHED, self::TYPE_PROGRESS, self::TYPE_SUCCESS, self::TYPE_ERROR];
    const POSSIBLE_FINISH_TYPES = [self::TYPE_SUCCESS, self::TYPE_ERROR];
    const STATUS_KEY = 'status';

    public function on(ReplyMessagePayload $messagePayload);
    public function onSuccess(ReplyMessagePayload $messagePayload);
    public function onError(ReplyMessagePayload $messagePayload);
    public function onProgress(ReplyMessagePayload $messagePayload);
    public function onFinish(ReplyMessagePayload $messagePayload);
}
