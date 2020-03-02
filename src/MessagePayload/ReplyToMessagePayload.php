<?php


namespace Ipedis\Rabbit\MessagePayload;


use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

class ReplyToMessagePayload extends MessagePayloadAbstract
{
    const HEADER_CORRELATION_ID = 'correlation_id';

    /**
     * @var string $taskId
     */
    private $taskId;

    public function __construct(string $channel, string $taskId, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        $this->taskId = $taskId;
        $this->addHeader(self::HEADER_CORRELATION_ID, $taskId);
    }

    /**
     * Factory method
     *
     * @param string $channel
     * @param string $taskId
     * @param array $data
     * @param array $headers
     * @return ReplyToMessagePayload
     */
    public static function build(string $channel, string $taskId, array $data = [], array $headers = []): self
    {
        return new self($channel, $taskId, $data, $headers);
    }

    /**
     * Factory method to create message payload from json
     *
     * @param string $msg
     * @return ReplyToMessagePayload
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
            !isset($msgBody['data'])
        ) {
            throw new MessagePayloadFormatException('Order message body format is invalid');
        }

        return new self(
            $msgBody['header'][self::HEADER_CHANNEL],
            $msgBody['header'][self::HEADER_CORRELATION_ID],
            $msgBody['data'],
            $msgBody['header']
        );
    }

    /**
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->taskId;
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
            'correlation_id' => $this->getTaskId()
        ];
    }
}
