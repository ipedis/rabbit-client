<?php


namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;

final class Task
{
    /**
     * @var OrderMessagePayload
     */
    private $message;
    /**
     * @var callable|null
     */
    private $callback;

    private function __construct(OrderMessagePayload $message, ?callable $callback = null)
    {

        $this->message = $message;
        $this->callback = $callback;
    }

    /**
     * @param OrderMessagePayload $message
     * @param callable|null $callback
     * @return static
     */
    public static function build(OrderMessagePayload $message, ?callable $callback = null): self
    {
        return new self($message, $callback);
    }

    /**
     * @return OrderMessagePayload
     */
    public function getMessage(): OrderMessagePayload
    {
        return $this->message;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    public function hasCallback(): bool
    {
        return is_callable($this->callback);
    }

}
