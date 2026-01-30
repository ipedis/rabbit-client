<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload;

use Exception;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;

/**
 * This class is responsible for standardizing the message body
 *
 */
abstract class MessagePayloadAbstract implements MessagePayloadInterface
{
    public const HEADER_UUID = 'uuid';

    public const HEADER_TIMESTAMP = 'sendAt';

    public const HEADER_TIMEZONE = 'timezone';

    public const HEADER_CHANNEL = 'channel';

    protected string $jsonEncodedData;

    /**
     * PayloadAbstract constructor.
     *
     * @throws Exception
     */
    protected function __construct(/**
         * Channel for the message
         */
        protected string $channel, /**
         * data storage
         */
        protected array $data = [], /**
         * header storage
         */
        protected array $headers = []
    ) {
        /**
         * Add channel to header
         */
        $this->setDefaultHeader();
        $this->addHeader(self::HEADER_CHANNEL, $this->channel);
        $this->jsonEncodedData = json_encode($this->getData());
    }

    /**
     * Factory method
     *
     * @throws \Exception
     */
    public static function build(string $channel, array $data = [], array $headers = []): self
    {
        return new static($channel, $data, $headers);
    }

    /**
     * Factory method to create message payload from json
     *
     * @return EventMessagePayload
     * @throws MessagePayloadFormatException
     */
    public static function fromJson(string $msg): self
    {
        $state = json_decode($msg, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MessagePayloadFormatException(sprintf('Event message body format is invalid : {%s}', $msg));
        }

        return static::fromArray($state);
    }

    abstract public static function fromArray(array $state): self;

    /**
     * @param $value
     */
    protected function addHeader(string $key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function hasData(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * json version of data
     */
    public function getStringifyData(): string
    {
        return $this->jsonEncodedData;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @alias
     */
    public function toArray(): array
    {
        return $this->getData();
    }

    /**
     * Get context of event without actual payload.
     */
    public function getContext(): array
    {
        return [
            self::HEADER_UUID => $this->getUuid(),
            self::HEADER_CHANNEL => $this->getChannel(),
            self::HEADER_TIMESTAMP => $this->getTimestamp(),
            self::HEADER_TIMEZONE => $this->getTimezone()
        ];
    }

    public function getUuid(): string
    {
        return $this->getHeader(self::HEADER_UUID);
    }

    /**
     * @return mixed|null
     */
    public function getHeader(string $key, $default = null)
    {
        return ($this->hasHeader($key)) ?
            $this->headers[$key] :
            $default;
    }

    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getTimestamp(): mixed
    {
        return $this->getHeader(self::HEADER_TIMESTAMP);
    }

    public function getTimezone(): array
    {
        return $this->getHeader(self::HEADER_TIMEZONE);
    }

    public function getTimezoneName(): string
    {
        return $this->getTimezone()['timezone'];
    }

    public function jsonSerialize(): array
    {
        return [
            'header' => $this->getHeaders(),
            'data' => $this->getData()
        ];
    }

    /**
     * Add missing headers before serializing
     *
     * @throws Exception
     */
    private function setDefaultHeader(): void
    {
        /**
         * Add random uuid to header if missing
         */
        if (!$this->hasHeader(self::HEADER_UUID)) {
            $this->headers[self::HEADER_UUID] = uuid_create();
        }

        /**
         * Add timestamp to header if missing
         */
        if (!$this->hasHeader(self::HEADER_TIMESTAMP)) {
            $this->headers[self::HEADER_TIMESTAMP] = microtime(true);
            $timezone = (new \DateTime())->getTimezone();
            $this->headers[self::HEADER_TIMEZONE] = [
                'timezone' => $timezone->getName()
            ];
        }
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
