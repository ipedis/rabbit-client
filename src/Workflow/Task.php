<?php

namespace Ipedis\Rabbit\Workflow;

use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;
use Ipedis\Rabbit\Exception\InvalidUuidException;
use Ipedis\Rabbit\Exception\Task\InvalidStatusException;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Utils\Helper\DateTimeHelper;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\TaskProgress;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Status;
use Ipedis\Rabbit\Workflow\ProgressBag\Property\Timer;

final class Task extends Bindable
{
    use DateTimeHelper;

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
        $this->status = MessageHandlerInterface::TYPE_PLANIFIED;
        $this->retryCount = 0;
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
     * @return string
     */
    public function getTaskId(): string
    {
        return $this->getOrderMessage()->getOrderId();
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
     * The task is in progress
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_PROGRESS;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Called when task execution starts
     */
    private function taskExecutionStarted(): void
    {
        $this->timeStart = $this->getCurrentDateTimeWithMicroseconds();
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
    public function isSuccess(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_SUCCESS;
    }

    /**
     * The task has failed
     *
     * @return bool
     */
    public function isOnFailure(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_ERROR;
    }

    /**
     * Called when task execution completes
     */
    private function taskExecutionCompleted(): void
    {
        $this->timeFinished = $this->getCurrentDateTimeWithMicroseconds();
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
        $this->retryCount++;

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
     * The task has been dispatched
     *
     * @return bool
     */
    public function isDispatched(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_DISPATCHED;
    }

    /**
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * @return TaskProgress
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     * @throws InvalidUuidException
     */
    public function getTaskProgress(): TaskProgress
    {
        return TaskProgress::build(
            $this->getOrderMessage()->getOrderId(),
            $this->getType(),
            $this->getProgressStatus(),
            $this->getTimer()
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return ChannelAbstract::getTypeFromChannelName($this->getOrderMessage()->getChannel());
    }

    /**
     * @return Status
     */
    public function getProgressStatus(): Status
    {
        switch ($this->getStatus()) {
            case MessageHandlerInterface::TYPE_PLANIFIED:
                return Status::buildPending();
            case MessageHandlerInterface::TYPE_SUCCESS:
                return Status::buildSuccess();
            case MessageHandlerInterface::TYPE_ERROR:
                return Status::buildFailed();
            case MessageHandlerInterface::TYPE_PROGRESS:
            case MessageHandlerInterface::TYPE_DISPATCHED:
                return Status::buildRunning();
        }
    }

    /**
     * @return Timer
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public function getTimer()
    {
        return Timer::build(
            $this->getExecutionTime(),
            $this->getStartTime(),
            $this->getFinishedTime()
        );
    }

    /**
     * Get task execution time
     * @return float
     */
    public function getExecutionTime(): float
    {
        if (is_null($this->timeStart) || is_null($this->timeFinished)) {
            return 0;
        }

        return $this->getDifferenceWithMicroseconds($this->timeStart, $this->timeFinished);
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
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::TASK_ALLOW_TYPES;
    }
}
