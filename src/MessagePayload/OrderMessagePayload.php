<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

class OrderMessagePayload extends MessagePayloadAbstract
{
    public const HEADER_CORRELATION_ID = 'correlation_id';

    public const HEADER_REPLY_QUEUE = 'replyQueue';

    /**
     * @var string $orderId
     */
    private $orderId;

    /**
     * @var string $replyQueue
     */
    private $replyQueue;

    protected function __construct(string $channel, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        if (isset($headers[self::HEADER_CORRELATION_ID])) {
            $this->orderId = $headers[self::HEADER_CORRELATION_ID];
        } else {
            $this->setOrderId(uuid_create());
        }

        if (isset($headers[self::HEADER_REPLY_QUEUE])) {
            $this->replyQueue = $headers[self::HEADER_REPLY_QUEUE];
        }
    }

    /**
     * @return static
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $state): self
    {
        if (
            !isset($state['header']) ||
            !isset($state['header'][self::HEADER_CHANNEL]) ||
            !isset($state['header'][self::HEADER_REPLY_QUEUE]) ||
            !isset($state['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($state['data'])
        ) {
            throw new MessagePayloadFormatException('Array structure is invalid');
        }

        return new self(
            $state['header'][self::HEADER_CHANNEL],
            $state['data'],
            $state['header']
        );
    }

    /**
     * Helper function
     * to return custom properties for rabbitmq message obj
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
    public function setOrderId(string $orderId): self
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
    public function setReplyQueue(string $replyQueue): self
    {
        $this->replyQueue = $replyQueue;
        $this->headers[self::HEADER_REPLY_QUEUE] = $replyQueue;

        return $this;
    }
}
