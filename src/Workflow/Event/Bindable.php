<?php


namespace Ipedis\Rabbit\Workflow\Event;



abstract class Bindable
{

    /**
     * @var callable[]
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
        if(!in_array($eventType, $this->getAllowedBindableTypes()))
            throw new \LogicException(sprintf("event type : %s is not allowed.", $eventType));

        if(!is_callable($callback))
            throw new \LogicException(sprintf("event type : %s parameter is not a callable", $eventType));

        $this->callbacks[$eventType] = $callback;

        return $this;
    }

    public function call(string $eventType, $payload = null): self
    {
        if(!empty($this->callbacks[$eventType]) && is_callable($this->callbacks[$eventType])) {
            if( is_null($payload) )
                $this->callbacks[$eventType]();
            else
                $this->callbacks[$eventType]($payload);
        }
        return $this;
    }

    /**
     * @param array $callbacks
     */
    protected function assertCallbacks(array $callbacks)
    {
        foreach ( $callbacks as $eventType => $callback ) {
            if(!in_array($eventType, $this->getAllowedBindableTypes()))
                throw new \LogicException(sprintf("event type : %s is not allowed.", $eventType));

            if(!is_callable($callback))
                throw new \LogicException(sprintf("event type : %s parameter is not a callable", $eventType));
        }
    }
}
