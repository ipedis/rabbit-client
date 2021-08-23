<?php

namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

class EventMessagePayload extends MessagePayloadAbstract
{
    /**
     * @param array
     * @return static
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $state): self
    {
        if (
            !isset($state['header']) ||
            !isset($state['header'][self::HEADER_CHANNEL]) ||
            !isset($state['data'])
        ) {
            throw new MessagePayloadFormatException('array structure is invalid');
        }

        return self::build(
            $state['header'][self::HEADER_CHANNEL],
            $state['data'],
            $state['header']
        );
    }
}
