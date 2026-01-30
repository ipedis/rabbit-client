<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use JsonSerializable;

interface MessagePayloadInterface extends JsonSerializable
{
    /**
     * Get headers
     */
    public function getHeaders(): array;

    /**
     * Get data
     */
    public function getData(): array;

    /**
     * Get channel on which to dispatch message
     */
    public function getChannel(): string;

    /**
     * Get stringified version of data
     */
    public function getStringifyData(): string;
}
