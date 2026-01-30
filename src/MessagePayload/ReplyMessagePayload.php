<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

final class ReplyMessagePayload extends MessagePayloadAbstract implements ReplyMessagePayloadInterface
{
    public const HEADER_CORRELATION_ID = 'correlation_id';

    public const HEADER_STATUS = 'status';

    public const HEADER_EXECTIME = 'executionTime';

    protected function __construct(
        string $channel,
        private readonly string $orderId,
        private readonly string $status,
        array $data = [],
        array $headers = []
    ) {
        parent::__construct($channel, $data, $headers);
        $this->addHeader(self::HEADER_CORRELATION_ID, $this->orderId);
        $this->addHeader(self::HEADER_STATUS, $this->status);

        if (!empty($headers[self::HEADER_EXECTIME])) {
            $this->addHeader(self::HEADER_EXECTIME, $headers[self::HEADER_EXECTIME]);
        }
    }

    /**
     * Factory method to build reply from order message
     *
     * @throws MessagePayloadFormatException
     */
    public static function buildFromOrderMessagePayload(
        OrderMessagePayload $orderMessagePayload,
        string $status,
        array $data = [],
        array $headers = []
    ): self {
        if (!in_array($status, MessageHandlerInterface::AVAILABLE_TYPES)) {
            throw new MessagePayloadFormatException(sprintf('Invalid Status {%s} for building reply message.', $status));
        }

        /**
         * Add order payload to data
         */
        $data = [ReplyMessagePayloadInterface::REPLY_INDEX => $data, 'orderPayload' => $orderMessagePayload->getData()];

        return new self(
            $orderMessagePayload->getReplyQueue(),
            $orderMessagePayload->getOrderId(),
            $status,
            $data,
            $headers
        );
    }

    /**
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $state): self
    {
        if (
            !isset($state['header']) ||
            !isset($state['header'][self::HEADER_CHANNEL]) ||
            !isset($state['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($state['header'][self::HEADER_STATUS]) ||
            !isset($state['data'])
        ) {
            throw new MessagePayloadFormatException('Array structure is invalid');
        }

        return new self(
            $state['header'][self::HEADER_CHANNEL],
            $state['header'][self::HEADER_CORRELATION_ID],
            $state['header'][self::HEADER_STATUS],
            $state['data'],
            $state['header']
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Helper function
     * to return custom properties for rabbitmq message obj
     */
    public function getMessageProperties(): array
    {
        return [
            'correlation_id' => $this->orderId
        ];
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @return mixed
     */
    public function getReply()
    {
        if ($this->hasReply()) {
            return $this->getData()[self::REPLY_INDEX];
        }
        return null;
    }

    public function hasReply(): bool
    {
        return $this->getData() !== [] && !empty($this->getData()[self::REPLY_INDEX]);
    }

    public function isError(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_ERROR;
    }

    public function isSuccess(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_SUCCESS;
    }
}
