<?php

namespace Ipedis\Rabbit\Exception\Helper;

use Exception;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use JsonSerializable;
use LogicException;

class Serializer implements JsonSerializable
{
    private Exception $exception;
    private Context $context;

    /**
     * Serializer constructor.
     * @param Exception $exception
     * @param Context $context
     */
    protected function __construct(Exception $exception, Context $context)
    {
        $this->exception = $exception;
        $this->context = $context;
    }

    /**
     * @param Exception $exception
     * @param ?Context $context
     * @return static
     */
    public static function fromException(Exception $exception, ?Context $context = null): self
    {
        return new static($exception, $context ?? Context::initialize());
    }

    public static function fromMessage(MessagePayloadInterface $message): Error
    {
        return Error::fromArray($message->getData()['error']);
    }

    /**
     * @param Context | array $context
     * @return $this
     */
    public function setContext($context): self
    {
        if (is_object($context) && $context instanceof Context) {
            $this->context = $context;
        } else if (is_array($context)) {
            $this->context = Context::fromArray($context);
        } else {
            throw new LogicException(sprintf('%s::setContext parameter must have type array or Context class.', get_class($this)));
        }

        return $this;
    }

    /**
     * @param string $name
     * @param string | int | object | array $state
     * @return $this
     */
    public function addContext(string $name, $state): self
    {
        Context::assertContext($state);
        $this->context->add($name, $state);
        return $this;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    protected function serializeException()
    {
        return [
           'message' => $this->exception->getMessage(),
           'code' => $this->exception->getCode()
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
           'context' => $this->context,
           'exception' => $this->serializeException()
       ];
    }
}
