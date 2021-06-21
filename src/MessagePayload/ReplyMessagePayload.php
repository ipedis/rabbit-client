<?php

namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

final class ReplyMessagePayload extends MessagePayloadAbstract
{
    public const HEADER_CORRELATION_ID = 'correlation_id';
    public const HEADER_STATUS = 'status';
    public const HEADER_EXECTIME = 'executionTime';

    /**
     * @var string $orderId
     */
    private $orderId;

    /**
     * @var string $status
     */
    private $status;

    protected function __construct(
        string $channel,
        string $taskId,
        string $status,
        array $data = [],
        array $headers = []
    ) {
        parent::__construct($channel, $data, $headers);

        $this->orderId = $taskId;
        $this->addHeader(self::HEADER_CORRELATION_ID, $taskId);

        $this->status = $status;
        $this->addHeader(self::HEADER_STATUS, $status);

        if (!empty($headers[self::HEADER_EXECTIME])) {
            $this->addHeader(self::HEADER_EXECTIME, $headers[self::HEADER_EXECTIME]);
        }
    }

    /**
     * Factory method to build reply from order message
     *
     * @param OrderMessagePayload $orderMessagePayload
     * @param string $status
     * @param array $data
     * @param array $headers
     * @return ReplyMessagePayload
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
        $data = array_merge($data, [
            'orderPayload' => $orderMessagePayload->getData()
        ]);

        return new self(
            $orderMessagePayload->getReplyQueue(),
            $orderMessagePayload->getOrderId(),
            $status,
            $data,
            $headers
        );
    }

    /**
     * Factory method to create message payload from json
     *
     * @param string $msg
     * @return ReplyMessagePayload
     * @throws MessagePayloadFormatException
     */
    public static function fromJson(string $msg): self
    {
        $msgBody = json_decode($msg, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !isset($msgBody['header']) ||
            !isset($msgBody['header'][self::HEADER_CHANNEL]) ||
            !isset($msgBody['header'][self::HEADER_CORRELATION_ID]) ||
            !isset($msgBody['header'][self::HEADER_STATUS]) ||
            !isset($msgBody['data'])
        ) {
            throw new MessagePayloadFormatException(sprintf('Order message body format is invalid : {%s}', $msg));
        }

        return new self(
            $msgBody['header'][self::HEADER_CHANNEL],
            $msgBody['header'][self::HEADER_CORRELATION_ID],
            $msgBody['header'][self::HEADER_STATUS],
            $msgBody['data'],
            $msgBody['header']
        );
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
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
            'correlation_id' => $this->getOrderId()
        ];
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }
}
