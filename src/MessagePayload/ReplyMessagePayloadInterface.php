<?php

namespace Ipedis\Rabbit\MessagePayload;

interface ReplyMessagePayloadInterface extends MessagePayloadInterface
{
    public const REPLY_INDEX = 'reply';

    public function getReply();
    public function hasReply(): bool;
}
