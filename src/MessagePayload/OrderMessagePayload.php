<?php


namespace Ipedis\Rabbit\MessagePayload;

class OrderMessagePayload extends MessagePayloadAbstract
{
    const HEADER_CORRELATION_ID = 'correlation_id';

    public function __construct(string $channel, ?string $correlation_id = null, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        $this->headers[self::HEADER_CORRELATION_ID] = $correlation_id;
    }
}
