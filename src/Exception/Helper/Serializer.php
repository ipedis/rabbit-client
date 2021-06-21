<?php


namespace Ipedis\Rabbit\Exception\Helper;

use Exception;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use JsonSerializable;
use LogicException;

class Serializer implements JsonSerializable
{
    private Exception $exception;
    private array $context = [];

    /**
     * Serializer constructor.
     * @param Exception $exception
     * @param array $context
     */
    protected function __construct(Exception $exception, array $context = [])
    {
        $this->exception = $exception;
        $this->assertContext($context);
        $this->context = $context;
    }

    /**
     * @param Exception $exception
     * @param array $context
     * @return static
     */
    public static function fromException(Exception $exception, array $context = []): self
    {
        return new static($exception, $context);
    }

    public static function fromMessage(MessagePayloadInterface $message): Error
    {
        return Error::fromArray($message->getData()['error']);
    }

    protected function assertContext($data)
    {
        if (is_iterable($data)) {
            foreach ($data as $item) {
                $this->assertContext($item);
            }
        } else if (is_object($data) && !($data instanceof JsonSerializable)) {
            throw new LogicException(sprintf('object with type "%s" can\'t be serialize as it is not implementing JsonSerializable', get_class($data)));
        }
    }

    /**
     * @param mixed $data
     */
    public function addContext($data) {
        $this->assertContext($data);
        $this->context[] = $data;
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
