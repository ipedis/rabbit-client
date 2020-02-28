<?php

namespace Ipedis\Rabbit\MessagePayload;


use JsonSerializable;

interface MessagePayloadInterface extends JsonSerializable
{
    public function getHeaders(): array;

    public function getData(): array;

    public function getChannel(): string;
}
