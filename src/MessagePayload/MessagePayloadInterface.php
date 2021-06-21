<?php

namespace Ipedis\Rabbit\MessagePayload;

use JsonSerializable;

interface MessagePayloadInterface extends JsonSerializable
{
    /**
     * Get headers
     *
     * @return array
     */
    public function getHeaders(): array;

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Get channel on which to dispatch message
     *
     * @return string
     */
    public function getChannel(): string;

    /**
     * Get stringified version of data
     *
     * @return string
     */
    public function getStringifyData(): string;
}
