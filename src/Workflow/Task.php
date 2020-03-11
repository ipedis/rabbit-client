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
    private $message;

    private function __construct(OrderMessagePayload $message, $callbacks = [])
    {
        $this->status = MessageHandlerInterface::TYPE_PLANIFIED;
        $this->message = $message;
        $this->assertCallbacks($callbacks);
        $this->callbacks = $callbacks;
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
    public function getMessage(): OrderMessagePayload
    {
        return $this->message;
    }

    public function transitionTo(string $newStatus) {
        if (!in_array($newStatus, MessageHandlerInterface::AVAILABLE_TYPES)) {
            throw new InvalidStatusException('type not allowed');
        }

        $this->status = $newStatus;
        $this->dispatchInternalEvent();
    }

    public function update(ReplyMessagePayload $message): self
    {
        $this->transitionTo($message->getStatus());

        return $this;
    }

    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::TASK_ALLOW_TYPES;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isFinished(): bool
    {
        return $this->isSuccess() || $this->isOnFailure();
    }

    public function isSuccess():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_SUCCESS;
    }

    public function isOnFailure():  bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_ERROR;
    }

    public function isInProgress(): bool
    {
        return $this->getStatus() === MessageHandlerInterface::TYPE_PROGRESS;
    }

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
