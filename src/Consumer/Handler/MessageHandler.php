<?php

namespace Ipedis\Rabbit\Consumer\Handler;


use AMQPEnvelope;
use AMQPQueue;
use Closure;
use Ipedis\Rabbit\DTO\Task\Task;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;


abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * Keep track of completed tasks
     *
     * @var array $completedTasks
     */
    protected $completedTasks = [];

    protected $dispatchedTasks = [];

    /**
     * Holds a collection of callable handlers to be
     * executed on defined events
     *
     * Only one handler is allowed for each event type
     *
     * @var array $eventHandlers
     */
    protected $eventHandlers = [];

    /**
     * The main method that gets executed
     * when implementing MessageHandlerInterface
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @return bool
     * @throws MessagePayloadFormatException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function on(AMQPEnvelope $message, AMQPQueue $q)
    {
        /**
         * Re-construct message payload from request body
         */
        $messagePayload = ReplyMessagePayload::fromJson($message->getBody());
        $data = $messagePayload->getData();

        $taskStatus = strtolower($data[self::STATUS_KEY]);

        switch ($taskStatus)
        {
            case self::TYPE_SUCCESS:
                $this->setTaskAsCompleted($messagePayload->getTaskId(), self::TYPE_SUCCESS);
                $this->onSuccess($messagePayload);
                break;
            case self::TYPE_ERROR:
                $this->setTaskAsCompleted($messagePayload->getTaskId(), self::TYPE_ERROR);
                $this->onError($messagePayload);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($messagePayload);
                break;
        }

        /**
         * Execute handler for current event if any
         */
        $this->executeEventHandler($taskStatus, $messagePayload);

        /**
         * An error or success eventually leads to completion
         */
        if ($taskStatus === self::TYPE_SUCCESS || $taskStatus === self::TYPE_ERROR) {
            $this->onFinish($messagePayload);
        }

        /**
         * wait for all replies
         */
        if (count($this->getCompletedTasks()) !== count($this->getDispatchedTasks())) {
            return true;
        }

        return false;
    }

    /**
     * Bind handler to allowed event
     *
     * @param string $event
     * @param Closure $handler
     * @return MessageHandler
     */
    public function bind(string $event, Closure $handler) : self
    {
        if (in_array($event, self::AVAILABLE_TYPES)) {
            $this->eventHandlers[$event] = $handler;
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
        return $this->completedTasks;
    }

    public function getDispatchedTasks(): array
    {
        return $this->dispatchedTasks;
    }

    public function addDispatchedTask($taskId)
    {
        $this->dispatchedTasks[] = $taskId;
    }

    /**
     * Get collection of successfully completed tasks
     *
     * @return array
     */
    public function getSuccessfulTasks(): array
    {
        return array_filter($this->completedTasks, function(Task $task) {
            return $task->getStatus() === self::TYPE_SUCCESS;
        });
    }

    /**
     * Get collection of completed tasks which have failed
     *
     * @return array
     */
    public function getFailedTasks(): array
    {
        return array_filter($this->completedTasks, function (Task $task) {
            return $task->getStatus() === self::TYPE_ERROR;
        });
    }

    /**
     * Add task to completion list
     *
     * @param string $taskId
     * @param string $status
     */
    private function setTaskAsCompleted(string $taskId, string $status)
    {
        $this->completedTasks[] = Task::build($taskId, $status);
    }

    /**
     * Helper function to execute handlers for event
     *
     * @param string $event
     * @param MessagePayloadInterface $messagePayload
     */
    private function executeEventHandler(string $event, MessagePayloadInterface $messagePayload)
    {
        if (isset($this->eventHandlers[$event])) {
            $handler = $this->eventHandlers[$event];
            $handler($messagePayload);
        }
    }
}
