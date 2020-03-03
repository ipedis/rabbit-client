<?php


namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
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
                break;
            case self::TYPE_ERROR:
                $this->setTaskAsCompleted($messagePayload->getTaskId());
                $this->onError($messagePayload);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($messagePayload);
                break;
        }

        /**
         * Execute binded handler for current event if any
         */
        $this->executeHandlersForEvent($taskStatus, $messagePayload);

        /**
         * An error or success eventually leads to finish
         */
        if ($taskStatus === self::TYPE_SUCCESS || $taskStatus === self::TYPE_ERROR) {
            $this->onFinish($messagePayload);
        }
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
            $this->bindedHandlers[$event] = $handler;
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

    /**
     * Add task id to complete collection
     *
     * @param string $taskId
     */
    private function setTaskAsCompleted(string $taskId)
    {
        $this->tasksCompleted[] = $taskId;
    }

    /**
     * Helper function to execute handlers for event
     *
     * @param string $event
     * @param MessagePayloadInterface $messagePayload
     */
    private function executeHandlersForEvent(string $event, MessagePayloadInterface $messagePayload)
    {
        if (isset($this->bindedHandlers[$event])) {
            $handler = $this->bindedHandlers[$event];
            $handler($messagePayload);
        }
    }
}
