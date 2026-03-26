<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

interface ReplyMessagePayloadInterface extends MessagePayloadInterface
{
    public const REPLY_INDEX = 'reply';

    public function getReply(): mixed;

    public function hasReply(): bool;
}
