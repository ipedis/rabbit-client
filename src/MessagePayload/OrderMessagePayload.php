<?php

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
     * Factory method
     *
     * @param string $channel
     * @param array $data
     * @param array $headers
     * @return OrderMessagePayload
     */
    public static function build(string $channel, array $data = [], array $headers = []): self
    {
        return new self($channel, $data, $headers);
    }

    /**
     * Factory method to create message payload from json
     *
     * @param string $msg
     * @return OrderMessagePayload
     * @throws MessagePayloadFormatException
     */
    public static function fromJson(string $msg): self
    {
        $msgBody = json_decode($msg, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !isset($msgBody['header']) ||
            !isset($msgBody['header'][self::HEADER_CHANNEL]) ||
            !isset($msgBody['header'][self::HEADER_REPLY_QUEUE]) ||
            !isset($msgBody['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($msgBody['data'])
        ) {
            throw new MessagePayloadFormatException(sprintf('Order message body format is invalid : {%s}', $msg));
        }

        return new self(
            $msgBody['header'][self::HEADER_CHANNEL],
            $msgBody['data'],
            $msgBody['header']
        );
    }

    /**
     * Helper function
     * to return custom properties for rabbitmq message obj
     *
     * @return array
     */
    public function getMessageProperties(): array
    {
        return [
            'correlation_id' => $this->getOrderId(),
            'reply_to' => $this->getReplyQueue()
        ];
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Set Task Id
     *
     * @param string $orderId
     * @return OrderMessagePayload
     */
    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;
        $this->headers[self::HEADER_CORRELATION_ID] = $orderId;

        return $this;
    }

    /**
     * @return string
     */
    public function getReplyQueue(): string
    {
        return $this->replyQueue;
    }

    /**
     * Set reply queue
     *
     * @param string $replyQueue
     * @return OrderMessagePayload
     */
    public function setReplyQueue(string $replyQueue): self
    {
        $this->replyQueue = $replyQueue;
        $this->headers[self::HEADER_REPLY_QUEUE] = $replyQueue;

        return $this;
    }
}
