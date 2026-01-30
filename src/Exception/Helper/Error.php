<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Exception\Helper;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use JsonSerializable;

class Error implements JsonSerializable
{
    /**
     * Error constructor.
     */
    protected function __construct(private array $exception, private readonly Context $context)
    {
    }

    /**
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $error): static
    {
        if (empty($error['exception'])) {
            throw new MessagePayloadFormatException('error message status must contain [error][exception]');
        }

        return new static(
            $error['exception'],
            Context::fromArray(empty($error['context']) ? [] : $error['context'])
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

    public function jsonSerialize(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->getContext(),
        ];
    }
}
