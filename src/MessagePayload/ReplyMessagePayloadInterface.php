<?php


namespace Ipedis\Rabbit\MessagePayload;


interface ReplyMessagePayloadInterface extends MessagePayloadInterface
{
    public const MESSAGE_INDEX = 'message';

    public function getMessage();
    public function hasMessage(): bool;
}
