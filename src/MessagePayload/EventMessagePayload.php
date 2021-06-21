<?php

namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

class EventMessagePayload extends MessagePayloadAbstract
{
    /**
     * Factory method
     *
     * @param string $channel
     * @param array $data
     * @param array $headers
     * @return EventMessagePayload
     */
    public static function build(string $channel, array $data = [], array $headers = []): self
    {
        return new self($channel, $data, $headers);
    }

    /**
     * Factory method to create message payload from json
     *
     * @param string $msg
     * @return EventMessagePayload
     * @throws MessagePayloadFormatException
     */
    public static function fromJson(string $msg): self
    {
        $msgBody = json_decode($msg, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !isset($msgBody['header']) ||
            !isset($msgBody['header'][self::HEADER_CHANNEL]) ||
            !isset($msgBody['data'])
        ) {
            throw new MessagePayloadFormatException(sprintf('Event message body format is invalid : {%s}', $msg));
        }

        return new self(
            $msgBody['header'][self::HEADER_CHANNEL],
            $msgBody['data'],
            $msgBody['header']
        );
    }
}
