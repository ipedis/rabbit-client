<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use JsonSerializable;

final class Error implements JsonSerializable
{
    /**
     * Error constructor.
     *
     * @param array{message: string, code: int} $exception
     */
    private function __construct(private array $exception, private readonly Context $context)
    {
    }

    /**
     * @param array<string, mixed> $error
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $error): self
    {
        if (empty($error['exception'])) {
            throw new MessagePayloadFormatException('error message status must contain [error][exception]');
        }

        /** @var array{message: string, code: int} $exception */
        $exception = $error['exception'];

        /** @var array<string, mixed> $context */
        $context = empty($error['context']) ? [] : $error['context'];

        return new self(
            $exception,
            Context::fromArray($context)
        );
    }

    public function getMessage(): string
    {
        return $this->exception['message'];
    }

    public function getCode(): int
    {
        return $this->exception['code'];
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function hasContext(): bool
    {
        return !$this->context->isEmpty();
    }

    /**
     * @return array{message: string, code: int, context: Context}
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
        ];
    }
}
