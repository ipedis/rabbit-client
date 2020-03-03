<?php


namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;


abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * Keep track of completed tasks
     *
     * @var array $tasksCompleted
     */
    protected $tasksCompleted = [];

    /**
     * Holds a collection of binded handlers
     * Each element of collection has the following format [
     *  event   => 'event',
     *  handler => callback()
     * ]
     *
     * @var array $bindedHandlers
     */
    protected $bindedHandlers = [];

    /**
     * @param AMQPMessage $req
     * @throws MessagePayloadFormatException
     */
    public function on(AMQPMessage $req)
    {
        /**
         * Create message payload objectValue from request body
         */
        $messagePayload = ReplyToMessagePayload::fromJson($req->getBody());
        $data = $messagePayload->getData();

        $taskStatus = strtolower($data[self::STATUS_KEY]);

        switch ($taskStatus)
        {
            case self::TYPE_SUCCESS:
                $this->setTaskAsCompleted($messagePayload->getTaskId());

                $this->onSuccess($messagePayload);
                $this->onFinish($messagePayload);
                break;
            case self::TYPE_ERROR:
                $this->setTaskAsCompleted($messagePayload->getTaskId());

                $this->onError($messagePayload);
                $this->onFinish($messagePayload);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($messagePayload);
                break;
        }

        /**
         * Execute binded handlers for current event
         */
        array_map(function($handler) use ($taskStatus, $messagePayload) {
            if ($handler['event'] === $taskStatus) {
                $callable = $handler['handler'];
                $callable($messagePayload);
            }

        }, $this->bindedHandlers);
    }

    /**
     * Bind handler to allowed events
     *
     * @param string $event
     * @param \Closure $handler
     * @return MessageHandler
     */
    public function bind(string $event, \Closure $handler) : self
    {
        if (in_array($event, self::AVAILABLE_TYPES)) {
            $this->bindedHandlers[] = [
                'event'   => $event,
                'handler' => $handler
            ];
        }

        return $this;
    }

    /**
     * Get collection of completed tasks
     *
     * @return array
     */
    public function getCompletedTasks(): array
    {
        return $this->tasksCompleted;
    }

    private function setTaskAsCompleted(string $taskId)
    {
        $this->tasksCompleted[] = $taskId;
    }
}
