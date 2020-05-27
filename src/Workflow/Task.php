<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;
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
     * @var \DateTime $timeStart
     */
    private $timeStart;

    /**
     * @var \DateTime $timeFinished
     */
    private $timeFinished;

    /**
     * @var OrderMessagePayload
     */
    private $orderMessage;

    /**
     * @var ReplyMessagePayload[]
     */
    private $replyMessages = [];

    /**
     * @var int
     */
    private $retryCount;

    private function __construct(OrderMessagePayload $orderMessage, $callbacks = [])
    {
        $this->status       = MessageHandlerInterface::TYPE_PLANIFIED;
        $this->retryCount   = 0;
        $this->orderMessage = $orderMessage;
        $this->callbacks    = $this->assertCallbacks($callbacks);
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
     * Retry dispatching task
     * - revert status to planified
     * - increment retry count
     *
     * @return Task
     * @throws InvalidStatusException
     */
    public function retry(): self
    {
        $this->transitionTo(MessageHandlerInterface::TYPE_PLANIFIED);
        $this->retryCount ++;

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

    public function getStatusType()
    {
        switch ($this->getStatus()) {
            case MessageHandlerInterface::TYPE_PLANIFIED:
                return StatusType::buildPending();
            case MessageHandlerInterface::TYPE_SUCCESS:
                return StatusType::buildSuccess();
            case MessageHandlerInterface::TYPE_ERROR:
                return StatusType::buildFailed();
            case MessageHandlerInterface::TYPE_PROGRESS:
            case MessageHandlerInterface::TYPE_DISPATCHED:
                return StatusType::buildRunning();
        }
    }

    /**
     * @return TimerType
     */
    public function getTimer()
    {
        return TimerType::build(
            $this->getExecutionTime(),
            $this->getStartTime(),
            $this->getFinishedTime()
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return ChannelAbstract::getTypeFromString($this->getOrderMessage()->getChannel());
    }

    /**
     * Get task execution time
     * @return float
     */
    public function getExecutionTime(): float
    {
        if (is_null($this->timeStart) || is_null($this->timeFinished)){
            return 0;
        }

        return $this->timeFinished->getTimestamp() - $this->timeStart->getTimestamp();
    }

    /**
     * @return \DateTime|null
     */
    public function getStartTime(): ?\DateTime
    {
        return $this->timeStart;
    }

    /**
     * @return \DateTime|null
     */
    public function getFinishedTime(): ?\DateTime
    {
        return $this->timeFinished;
    }

    /**
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getSummary()
    {
        return TaskType::build(
            $this->getOrderMessage()->getOrderId(),
            $this->getType(),
            $this->getStatusType(),
            $this->getTimer()
        );
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
        if ($this->isInProgress() && is_null($this->timeStart)) {
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
        $this->timeStart = new \DateTime();
    }

    /**
     * Called when task execution completes
     */
    private function taskExecutionCompleted(): void
    {
        $this->timeFinished = new \DateTime();
    }

}
