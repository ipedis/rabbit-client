<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Exception;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

/** @phpstan-consistent-constructor */
class EventMessagePayload extends MessagePayloadAbstract
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     *
     * @throws Exception
     */
    public static function build(string $channel, array $data = [], array $headers = []): static
    {
        return new static($channel, $data, $headers);
    }

    /**
     * @param array<string, mixed> $state
     *
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $state): static
    {
        if (
            !isset($state['header']) ||
            !is_array($state['header']) ||
            !isset($state['header'][self::HEADER_CHANNEL]) ||
            !is_string($state['header'][self::HEADER_CHANNEL]) ||
            !isset($state['data']) ||
            !is_array($state['data'])
        ) {
            throw new MessagePayloadFormatException('array structure is invalid');
        }

        $channel = $state['header'][self::HEADER_CHANNEL];
        /** @var array<string, mixed> $header */
        $header = $state['header'];
        /** @var array<string, mixed> $data */
        $data = $state['data'];

        return static::build(
            $channel,
            $data,
            $header
        );
    }
}
