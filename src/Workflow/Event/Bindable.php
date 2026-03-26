<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\Event;

use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayloadInterface;

abstract class Bindable
{
    /**
     * @var array<string, list<callable>>
     */
    protected array $callbacks = [];

    public function bind(string $eventType, callable $callback): static
    {
        if (!in_array($eventType, $this->getAllowedBindableTypes())) {
            throw new \LogicException(sprintf("event type : %s is not allowed.", $eventType));
        }

        if (empty($this->callbacks[$eventType])) {
            $this->callbacks[$eventType] = [];
        }

        $this->callbacks[$eventType][] = $callback;

        return $this;
    }

    /**
     * @return list<string>
     */
    abstract protected function getAllowedBindableTypes(): array;

    public function call(string $eventType, mixed $payload = null): static
    {
        // Ignore undefined array.
        if (empty($this->callbacks[$eventType])) {
            return $this;
        }

        foreach ($this->callbacks[$eventType] as $callback) {
            // if you do not have any payload, we only provide the event type.
            if (is_null($payload)) {
                $callback($eventType);
            } elseif (
                // TODO we should have Interface to materialize Bindable Class which also can have Error.
                // if we are on case error.
                preg_match('#(?:failed|error)$#', $eventType) &&
                is_object($payload) &&
                method_exists($payload, 'getLastReplyMessage')
            ) {
                $replyMessage = $payload->getLastReplyMessage();
                assert($replyMessage instanceof ReplyMessagePayloadInterface);
                $callback($payload, $eventType, Serializer::fromMessage($replyMessage));
            } else {
                // any other callable type.
                $callback($payload, $eventType);
            }
        }

        return $this;
    }

    /**
     * @param array<string, callable|list<callable>> $callbacks
     * @return array<string, list<callable>>
     */
    protected function assertCallbacks(array $callbacks): array
    {
        /** @var array<string, list<callable>> $result */
        $result = [];

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

            /** @var list<callable(): mixed> $callback */
            $result[$eventType] = $callback;
        }

        return $result;
    }
}
