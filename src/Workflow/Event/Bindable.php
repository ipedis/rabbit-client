<?php

namespace Ipedis\Rabbit\Workflow\Event;

abstract class Bindable
{
    /**
     * @var callable[][]
     */
    protected $callbacks = [];

    /**
     * @return string[]
     */
    abstract protected function getAllowedBindableTypes(): array;

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
            // if we have real callable.
            if (is_callable($callback)) {
                if (is_null($payload)) {
                    $callback($eventType);
                } else {
                    $callback($payload, $eventType);
                }
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
