<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Exception;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

final class ReplyMessagePayload extends MessagePayloadAbstract implements ReplyMessagePayloadInterface
{
    public const HEADER_CORRELATION_ID = 'correlation_id';

    public const HEADER_STATUS = 'status';

    public const HEADER_EXECTIME = 'executionTime';

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     *
     * @throws Exception
     */
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
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     *
     * @throws Exception
     */
    public static function build(string $channel, array $data = [], array $headers = []): static
    {
        throw new \LogicException('Use buildFromOrderMessagePayload() or fromArray() instead.');
    }

    /**
     * Factory method to build reply from order message
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     *
     * @throws MessagePayloadFormatException
     */
    public static function buildFromOrderMessagePayload(
        OrderMessagePayload $orderMessagePayload,
        string $status,
        array $data = [],
        array $headers = []
    ): self {
        if (!in_array($status, MessageHandlerInterface::AVAILABLE_TYPES, true)) {
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
            !isset($state['header'][self::HEADER_CORRELATION_ID]) ||
            !is_string($state['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($state['header'][self::HEADER_STATUS]) ||
            !is_string($state['header'][self::HEADER_STATUS]) ||
            !isset($state['data']) ||
            !is_array($state['data'])
        ) {
            throw new MessagePayloadFormatException('Array structure is invalid');
        }

        $channel = $state['header'][self::HEADER_CHANNEL];
        $correlationId = $state['header'][self::HEADER_CORRELATION_ID];
        $status = $state['header'][self::HEADER_STATUS];
        /** @var array<string, mixed> $header */
        $header = $state['header'];
        /** @var array<string, mixed> $data */
        $data = $state['data'];

        return new self(
            $channel,
            $correlationId,
            $status,
            $data,
            $header
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Helper function
     * to return custom properties for rabbitmq message obj
     *
     * @return array{correlation_id: string}
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

    public function getReply(): mixed
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
