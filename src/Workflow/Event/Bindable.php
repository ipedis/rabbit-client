<?php

namespace Ipedis\Rabbit\Workflow\Event;

use Ipedis\Rabbit\Exception\Helper\Serializer;

abstract class Bindable
{
    /**
     * @var callable[][]
     */
    protected array $callbacks = [];

    /**
     * @param string $eventType
     * @param callable $callback
     * @return static
     */
    public function bind(string $eventType, callable $callback): self
    {
        if (!in_array($eventType, $this->getAllowedBindableTypes())) {
            throw new \LogicException(sprintf("event type : %s is not allowed.", $eventType));
        }

        if (!is_callable($callback)) {
            throw new \LogicException(sprintf("event type : %s parameter is not a callable", $eventType));
        }

        if (empty($this->callbacks[$eventType])) {
            $this->callbacks[$eventType] = [];
        }

        $this->callbacks[$eventType][] = $callback;

        return $this;
    }

    /**
     * @return string[]
     */
    abstract protected function getAllowedBindableTypes(): array;

    /**
     * @param string $eventType
     * @param null $payload
     * @return static
     */
    public function call(string $eventType, $payload = null): self
    {
        // Ignore undefined array.
        if (empty($this->callbacks[$eventType])) {
            return $this;
        }
        // If is not array but pure callable, cast it to array.
        if (is_callable($this->callbacks[$eventType])) {
            $this->callbacks[$eventType] = [$this->callbacks[$eventType]];
        }

        foreach ($this->callbacks[$eventType] as $callback) {
            //ignore evey type which is not callable.
            if (!is_callable($callback)) continue;

            // if you do not have any payload, we only provide the event type.
            if (is_null($payload)) {
                $callback($eventType);
            } else if(
                // if we are on case error.
                preg_match('#(?:failed|error)$#',$eventType) &&
                method_exists($payload, 'getLastReplyMessage')
            ) {
                $callback($payload, $eventType, Serializer::fromMessage($payload->getLastReplyMessage()));
            }
            else {
                // any other callable type.
                $callback($payload, $eventType);
            }

        }

        return $this;
    }

    /**
     * @param array $callbacks
     * @return array
     */
    protected function assertCallbacks(array $callbacks): array
    {
        foreach ($callbacks as $eventType => $callback) {
            if (!in_array($eventType, $this->getAllowedBindableTypes())) {
                throw new \LogicException(sprintf("event type : %s is not allowed.", $eventType));
            }

            /**
             *  Force plain params as array of params.
             */
            if (!is_array($callback)) {
                $callback = [$callback];
            }

            foreach ($callback as $item) {
                if (!is_callable($item)) {
                    throw new \LogicException(sprintf("event type : %s parameter is not a callable or array of callable", $eventType));
                }
            }
        }
        return $callbacks;
    }
}
