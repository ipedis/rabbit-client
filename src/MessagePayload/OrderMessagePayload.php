<?php


namespace Ipedis\Rabbit\MessagePayload;

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

    public function __construct(string $channel, string $replyQueue, array $data = [], array $headers = [])
    {
        parent::__construct($channel, $data, $headers);

        /**
         * Generate unique uuid for task
         */
        $this->taskId = uuid_create();
        $this->addHeader(self::HEADER_CORRELATION_ID, $this->taskId);

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
