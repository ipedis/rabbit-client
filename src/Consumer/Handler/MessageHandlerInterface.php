<?php

namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

interface MessageHandlerInterface
{
    public const TYPE_PLANIFIED    = 'planified';
    public const TYPE_DISPATCHED   = 'dispatched';
    public const TYPE_PROGRESS     = 'progress';
    public const TYPE_SUCCESS      = 'success';
    public const TYPE_ERROR        = 'error';
    public const AVAILABLE_TYPES   = [self::TYPE_PLANIFIED, self::TYPE_DISPATCHED, self::TYPE_PROGRESS, self::TYPE_SUCCESS, self::TYPE_ERROR];
    public const POSSIBLE_FINISH_TYPES = [self::TYPE_SUCCESS, self::TYPE_ERROR];
    public const STATUS_KEY = 'status';

    public function on(ReplyMessagePayload $messagePayload);
    public function onSuccess(ReplyMessagePayload $messagePayload);
    public function onError(ReplyMessagePayload $messagePayload);
    public function onProgress(ReplyMessagePayload $messagePayload);
    public function onFinish(ReplyMessagePayload $messagePayload);
}
