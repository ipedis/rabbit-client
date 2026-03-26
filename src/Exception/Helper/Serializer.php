<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use Exception;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayloadInterface;
use JsonSerializable;

final class Serializer implements JsonSerializable
{
    /**
     * Serializer constructor.
     */
    private function __construct(private readonly Exception $exception, private Context $context)
    {
    }

    public static function fromException(Exception $exception, ?Context $context = null): self
    {
        return new self($exception, $context ?? Context::initialize());
    }

    public static function fromMessage(ReplyMessagePayloadInterface $message): Error
    {
        /** @var array<string, mixed> $reply */
        $reply = $message->getReply();

        /** @var array<string, mixed> $error */
        $error = $reply['error'];

        return Error::fromArray($error);
    }

    /**
     * @param Context|array<string, mixed> $context
     * @return $this
     */
    public function setContext(Context|array $context): self
    {
        if ($context instanceof Context) {
            $this->context = $context;
        } else {
            $this->context = Context::fromArray($context);
        }

        return $this;
    }

    /**
     * @param string|int|object|array<string, mixed> $state
     * @return $this
     */
    public function addContext(string $name, string|int|object|array $state): self
    {
        Context::assertContext($state);
        $this->context->add($name, $state);
        return $this;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array{message: string, code: int, className: string}
     */
    private function serializeException(): array
    {
        return [
           'message' => $this->exception->getMessage(),
           'code' => $this->exception->getCode(),
           'className' => $this->exception::class
        ];
    }

    /**
     * @return array{context: Context, exception: array{message: string, code: int, className: string}}
     */
    public function jsonSerialize(): array
    {
        return [
           'context' => $this->context,
           'exception' => $this->serializeException()
       ];
    }
}
