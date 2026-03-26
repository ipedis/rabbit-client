<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Exception;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

/** @phpstan-consistent-constructor */
class OrderMessagePayload extends MessagePayloadAbstract
{
    public const HEADER_CORRELATION_ID = 'correlation_id';

    public const HEADER_REPLY_QUEUE = 'replyQueue';

    private string $orderId;

    private string $replyQueue;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     *
     * @throws Exception
     */
    protected function __construct(string $channel, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        if (isset($headers[self::HEADER_CORRELATION_ID]) && is_string($headers[self::HEADER_CORRELATION_ID])) {
            $this->orderId = $headers[self::HEADER_CORRELATION_ID];
        } else {
            /** @var string $uuid */
            $uuid = uuid_create();
            $this->setOrderId($uuid);
        }

        if (isset($headers[self::HEADER_REPLY_QUEUE]) && is_string($headers[self::HEADER_REPLY_QUEUE])) {
            $this->replyQueue = $headers[self::HEADER_REPLY_QUEUE];
        }
    }

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
            !isset($state['header'][self::HEADER_REPLY_QUEUE]) ||
            !is_string($state['header'][self::HEADER_REPLY_QUEUE]) ||
            !isset($state['header'][self::HEADER_CORRELATION_ID]) ||
            !is_string($state['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($state['data']) ||
            !is_array($state['data'])
        ) {
            throw new MessagePayloadFormatException('Array structure is invalid');
        }

        $channel = $state['header'][self::HEADER_CHANNEL];
        /** @var array<string, mixed> $header */
        $header = $state['header'];
        /** @var array<string, mixed> $data */
        $data = $state['data'];

        return new static(
            $channel,
            $data,
            $header
        );
    }

    /**
     * Helper function
     * to return custom properties for rabbitmq message obj
     *
     * @return array{correlation_id: string, reply_to: string}
     */
    public function getMessageProperties(): array
    {
        return [
            'correlation_id' => $this->getOrderId(),
            'reply_to' => $this->getReplyQueue()
        ];
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Set Task Id
     */
    public function setOrderId(string $orderId): static
    {
        $this->orderId = $orderId;
        $this->headers[self::HEADER_CORRELATION_ID] = $orderId;

        return $this;
    }

    public function getReplyQueue(): string
    {
        return $this->replyQueue;
    }

    /**
     * Set reply queue
     */
    public function setReplyQueue(string $replyQueue): static
    {
        $this->replyQueue = $replyQueue;
        $this->headers[self::HEADER_REPLY_QUEUE] = $replyQueue;

        return $this;
    }
}
