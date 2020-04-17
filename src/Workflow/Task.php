<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;

final class Task extends Bindable
{
    /**
     * @var string
     */
    private $status;

    /**
     * @var float $timeStart
     */
    private $timeStart = 0;

    /**
     * @var float $timeFinished
     */
    private $timeFinished = 0;

    /**
     * @var OrderMessagePayload
     */
    private $orderMessage;

    /**
     * @var ReplyMessagePayload[]
     */
    private $replyMessages = [];

    private function __construct(OrderMessagePayload $orderMessage, $callbacks = [])
    {
        $this->status = MessageHandlerInterface::TYPE_PLANIFIED;
        $this->orderMessage = $orderMessage;
        $this->callbacks = $this->assertCallbacks($callbacks);
    }

    /**
     * @param OrderMessagePayload $message
     * @param array $callbacks
     * @return static
     */
    public static function build(OrderMessagePayload $message, array $callbacks = []): self
    {
        return new self($message, $callbacks);
    }

    /**
     * @return OrderMessagePayload
     */
    public function getOrderMessage(): OrderMessagePayload
    {
        return $this->orderMessage;
    }

    /**
     * @param ReplyMessagePayload $message
     * @return $this
     * @throws InvalidStatusException
     */
    public function update(ReplyMessagePayload $message): self
    {
        $this->replyMessages[] = $message;
        $this->transitionTo($message->getStatus());

        return $this;
    }

    /**
     * Task status changed to DISPATCHED
     */
    public function setTaskAsDispatched()
    {
        if ($this->isPlanified()) {
            $this->transitionTo(MessageHandlerInterface::TYPE_DISPATCHED);
        }
    }

    /**
     * @return array
     */
    public function getReplyMessages(): array
    {
        return $this->replyMessages;
    }

    /**
     * @return ReplyMessagePayload|null
     */
    public function getLastReplyMessage(): ?ReplyMessagePayload
    {
        return $this->hasReplyMessage() ? $this->replyMessages[count($this->replyMessages) - 1] : null;
    }

    /**
     * @return bool
     */
    public function hasReplyMessage(): bool
    {
        return !empty($this->replyMessages);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * The task has completed either with success
     * or falure
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->isSuccess() || $this->isOnFailure();
    }

    /**
     * The task has completed successfully
     *
     * @return bool
     */
    public function isSuccess():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_SUCCESS;
    }

    /**
     * The task has failed
     *
     * @return bool
     */
    public function isOnFailure():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_ERROR;
    }

    /**
     * The task is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_PROGRESS;
    }

    /**
     * The task has been dispatched
     *
     * @return bool
     */
    public function isDispatched(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_DISPATCHED;
    }

    /**
     * The task has been planified and
     * not yet dispatched
     *
     * @return bool
     */
    public function isPlanified(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_PLANIFIED;
    }

    /**
     * Get task execution time
     * @return float
     */
    public function getExecutionTime(): float
    {
        if ($this->timeStart === 0 || $this->timeFinished === 0) {
            return 0;
        }

        return $this->timeFinished - $this->timeStart;
    }

    /**
     * @param string $newStatus
     * @throws InvalidStatusException
     */
    protected function transitionTo(string $newStatus)
    {
        if (!in_array($newStatus, MessageHandlerInterface::AVAILABLE_TYPES)) {
            throw new InvalidStatusException('type not allowed');
        }

        $this->status = $newStatus;

        /**
         * Track execution start and execution complete
         */
        if ($this->isInProgress() && $this->timeStart === 0) {
            $this->taskExecutionStarted();
        } elseif ($this->isCompleted()) {
            $this->taskExecutionCompleted();
        }
    }

    /**
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::TASK_ALLOW_TYPES;
    }

    /**
     * Called when task execution starts
     */
    private function taskExecutionStarted(): void
    {
        $this->timeStart = microtime(true);
    }

    /**
     * Called when task execution completes
     */
    private function taskExecutionCompleted(): void
    {
        $this->timeFinished = microtime(true);
    }

}
