<?php

namespace Ipedis\Rabbit\Exception\Helper;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use JsonSerializable;

class Error implements JsonSerializable
{
    private array $exception;
    private Context $context;

    /**
     * Error constructor.
     * @param array $exception
     * @param Context $context
     */
    protected function __construct(array $exception, Context $context)
    {
        $this->exception = $exception;
        $this->context = $context;
    }

    /**
     * @param array $error
     * @return static
     * @throws MessagePayloadFormatException
     */
    public static function fromArray(array $error)
    {
        if (empty($error['exception'])) {
            throw new MessagePayloadFormatException('error message status must contain [error][exception]');
        }
        return new static(
            $error['exception'],
            Context::fromArray(empty($error['context']) ? [] : $error['context'])
        );
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->exception['message'];
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->exception['code'];
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return bool
     */
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
