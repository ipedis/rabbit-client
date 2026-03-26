<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

interface MessageHandlerInterface
{
    public const TYPE_PLANIFIED = 'planified';

    public const TYPE_DISPATCHED = 'dispatched';

    public const TYPE_STARTING = 'starting';

    public const TYPE_PROGRESS = 'progress';

    public const TYPE_SUCCESS = 'success';

    public const TYPE_ERROR = 'error';

    public const AVAILABLE_TYPES = [self::TYPE_STARTING, self::TYPE_PLANIFIED, self::TYPE_DISPATCHED, self::TYPE_PROGRESS, self::TYPE_SUCCESS, self::TYPE_ERROR];

    public const POSSIBLE_FINISH_TYPES = [self::TYPE_SUCCESS, self::TYPE_ERROR];

    public const STATUS_KEY = 'status';

    public function on(ReplyMessagePayload $message): void;

    public function onSuccess(ReplyMessagePayload $messagePayload): void;

    public function onError(ReplyMessagePayload $messagePayload, Error $error): void;

    public function onProgress(ReplyMessagePayload $messagePayload): void;

    public function onFinish(ReplyMessagePayload $messagePayload): void;

    public function onStarting(ReplyMessagePayload $messagePayload): void;
}
