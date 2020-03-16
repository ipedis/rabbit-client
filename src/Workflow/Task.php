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
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->isSuccess() || $this->isOnFailure();
    }

    /**
     * @return bool
     */
    public function isSuccess():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_SUCCESS;
    }

    /**
     * @return bool
     */
    public function isOnFailure():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_ERROR;
    }

    /**
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_PROGRESS;
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
        $this->dispatchInternalEvent();
    }
    /**
     * @return array
     */
    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::TASK_ALLOW_TYPES;
    }

    /**
     * internal call for binded event.
     */
    private function dispatchInternalEvent()
    {
        if($this->isInProgress()) {
            $this->call(BindableEventInterface::TASK_PROGRESS, $this);
        } elseif ($this->isSuccess()) {
            $this->call(BindableEventInterface::TASK_SUCCESS, $this);
        } elseif ($this->isOnFailure()) {
            $this->call(BindableEventInterface::TASK_FAILURE, $this);
        }

        if($this->isFinished()) {
            $this->call(BindableEventInterface::TASK_FINISH, $this);
        }
    }
}
