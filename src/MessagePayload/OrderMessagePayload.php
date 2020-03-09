<?php


namespace Ipedis\Rabbit\MessagePayload;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

class OrderMessagePayload extends MessagePayloadAbstract
{
    const HEADER_CORRELATION_ID = 'correlation_id';
    const HEADER_REPLY_QUEUE    = 'replyQueue';

    /**
     * @var string $taskId
     */
    private $taskId;

    /**
     * @var string $replyQueue
     */
    private $replyQueue;

    protected function __construct(string $channel, string $replyQueue, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        if (empty($headers[self::HEADER_CORRELATION_ID])) {
            /**
             * Generate unique uuid for task
             */
            $this->taskId = uuid_create();
            $this->addHeader(self::HEADER_CORRELATION_ID, $this->taskId);
        } else {
            /**
             * Headers already has taskid
             */
            $this->taskId = $headers[self::HEADER_CORRELATION_ID];
        }

        /**
         * Add reply queue to header
         */
        $this->replyQueue = $replyQueue;
        $this->addHeader(self::HEADER_REPLY_QUEUE, $this->replyQueue);
    }

    /**
     * Factory method
     *
     * @param string $channel
     * @param string $replyQueue
     * @param array $data
     * @param array $headers
     * @return OrderMessagePayload
     */
    public static function build(string $channel, string $replyQueue, array $data = [], array $headers = []): self
    {
        return new self($channel, $replyQueue, $data, $headers);
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
            $msgBody['header'][self::HEADER_REPLY_QUEUE],
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
     * @return string
     */
    public function getReplyQueue(): string
    {
        return $this->replyQueue;
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
            'correlation_id' => $this->getTaskId(),
            'reply_to'       => $this->getReplyQueue()
        ];
    }
}
