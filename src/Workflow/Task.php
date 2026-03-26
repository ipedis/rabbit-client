<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow;

use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
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

    private string $status = MessageHandlerInterface::TYPE_PLANIFIED;

    private ?\DateTime $timeStart = null;

    private ?\DateTime $timeFinished = null;

    /**
     * @var list<ReplyMessagePayload>
     */
    private array $replyMessages = [];

    private int $retryCount = 0;

    /**
     * @param array<string, callable|list<callable>> $callbacks
     */
    private function __construct(private OrderMessagePayload $orderMessage, array $callbacks = [])
    {
        $this->callbacks = $this->assertCallbacks($callbacks);
    }

    /**
     * @param array<string, callable|list<callable>> $callbacks
     */
    public static function build(OrderMessagePayload $message, array $callbacks = []): self
    {
        return new self($message, $callbacks);
    }

    public function getTaskId(): string
    {
        return $this->orderMessage->getOrderId();
    }

    public function getOrderMessage(): OrderMessagePayload
    {
        return $this->orderMessage;
    }

    /**
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
     * @throws InvalidStatusException
     */
    private function transitionTo(string $newStatus): void
    {
        if (!in_array($newStatus, MessageHandlerInterface::AVAILABLE_TYPES, true)) {
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
     */
    public function isInProgress(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_PROGRESS;
    }

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
     */
    public function isCompleted(): bool
    {
        if ($this->isSuccess()) {
            return true;
        }

        return $this->isOnFailure();
    }

    /**
     * The task has completed successfully
     */
    public function isSuccess(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_SUCCESS;
    }

    /**
     * The task has failed
     */
    public function isOnFailure(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_ERROR;
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
     * @throws InvalidStatusException
     */
    public function retry(): self
    {
        $this->transitionTo(MessageHandlerInterface::TYPE_PLANIFIED);
        ++$this->retryCount;

        return $this;
    }

    /**
     * Task status changed to DISPATCHED
     */
    public function setTaskAsDispatched(): void
    {
        if ($this->isPlanified()) {
            $this->transitionTo(MessageHandlerInterface::TYPE_DISPATCHED);
        }
    }

    /**
     * The task has been planified and
     * not yet dispatched
     */
    public function isPlanified(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_PLANIFIED;
    }

    /**
     * @return list<ReplyMessagePayload>
     */
    public function getReplyMessages(): array
    {
        return $this->replyMessages;
    }

    public function getLastReplyMessage(): ?ReplyMessagePayload
    {
        return $this->hasReplyMessage() ? $this->replyMessages[count($this->replyMessages) - 1] : null;
    }

    public function hasReplyMessage(): bool
    {
        return $this->replyMessages !== [];
    }

    /**
     * The task has been dispatched
     */
    public function isDispatched(): bool
    {
        return $this->status === MessageHandlerInterface::TYPE_DISPATCHED;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     * @throws InvalidUuidException
     */
    public function getTaskProgress(): TaskProgress
    {
        return TaskProgress::build(
            $this->orderMessage->getOrderId(),
            $this->getType(),
            $this->getProgressStatus(),
            $this->getTimer()
        );
    }

    public function getType(): string
    {
        return ChannelAbstract::getTypeFromChannelName($this->orderMessage->getChannel());
    }

    public function getProgressStatus(): Status
    {
        return match ($this->status) {
            MessageHandlerInterface::TYPE_PLANIFIED, MessageHandlerInterface::TYPE_STARTING => Status::buildPending(),
            MessageHandlerInterface::TYPE_SUCCESS => Status::buildSuccess(),
            MessageHandlerInterface::TYPE_ERROR => Status::buildFailed(),
            default => Status::buildRunning(),
        };
    }

    /**
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public function getTimer(): Timer
    {
        return Timer::build(
            $this->getExecutionTime(),
            $this->timeStart,
            $this->timeFinished
        );
    }

    /**
     * Get task execution time
     */
    public function getExecutionTime(): float
    {
        if (is_null($this->timeStart) || is_null($this->timeFinished)) {
            return 0;
        }

        return $this->getDifferenceWithMicroseconds($this->timeStart, $this->timeFinished);
    }

    public function getStartTime(): ?\DateTime
    {
        return $this->timeStart;
    }

    public function getFinishedTime(): ?\DateTime
    {
        return $this->timeFinished;
    }

    /**
     * @return list<string>
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::TASK_ALLOW_TYPES;
    }
}
