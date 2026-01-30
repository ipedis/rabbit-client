<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use Exception;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayloadInterface;
use JsonSerializable;
use LogicException;

class Serializer implements JsonSerializable
{
    /**
     * Serializer constructor.
     */
    protected function __construct(private readonly Exception $exception, private Context $context)
    {
    }

    /**
     * @return static
     */
    public static function fromException(Exception $exception, ?Context $context = null): self
    {
        return new static($exception, $context ?? Context::initialize());
    }

    public static function fromMessage(ReplyMessagePayloadInterface $message): Error
    {
        return Error::fromArray($message->getReply()['error']);
    }

    /**
     * @param Context | array $context
     * @return $this
     */
    public function setContext($context): self
    {
        if ($context instanceof Context) {
            $this->context = $context;
        } elseif (is_array($context)) {
            $this->context = Context::fromArray($context);
        } else {
            throw new LogicException(sprintf('%s::setContext parameter must have type array or Context class.', static::class));
        }

        return $this;
    }

    /**
     * @param string | int | object | array $state
     * @return $this
     */
    public function addContext(string $name, $state): self
    {
        Context::assertContext($state);
        $this->context->add($name, $state);
        return $this;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    protected function serializeException(): array
    {
        return [
           'message' => $this->exception->getMessage(),
           'code' => $this->exception->getCode(),
           'className' => $this->exception::class
        ];
    }

    public function jsonSerialize(): array
    {
        return [
           'context' => $this->context,
           'exception' => $this->serializeException()
       ];
    }
}
