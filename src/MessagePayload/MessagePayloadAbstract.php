<?php

namespace Ipedis\Rabbit\MessagePayload;

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

    /**
     * data storage
     *
     * @var array
     */
    protected array $data;

    /**
     * header storage
     *
     * @var array
     */
    protected array $headers;

    /**
     * Channel for the message
     *
     * @var string
     */
    protected string $channel;

    /**
     * PayloadAbstract constructor.
     *
     * @param string $channel
     * @param array $data
     * @param array $headers
     */
    protected function __construct(string $channel, array $data = [], array $headers = [])
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->channel = $channel;

        /**
         * Add channel to header
         */
        $this->setDefaultHeader();
        $this->addHeader(self::HEADER_CHANNEL, $channel);
    }

    /**
     * @param string $key
     * @param $value
     * @return MessagePayloadAbstract
     */
    protected function addHeader(string $key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasData(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * json version of data
     *
     * @return string
     */
    public function getStringifyData(): string
    {
        return json_encode($this->getData());
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @alias
     * @return array
     */
    public function toArray(): array
    {
        return $this->getData();
    }

    /**
     * Get context of event without actual payload.
     *
     * @return array
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
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getHeader(string $key, $default = null)
    {
        return ($this->hasHeader($key)) ?
            $this->headers[$key] :
            $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    /**
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getTimestamp(): int
    {
        return $this->getHeader(self::HEADER_TIMESTAMP);
    }

    public function getTimezone(): array
    {
        return $this->getHeader(self::HEADER_TIMEZONE);
    }

    public function jsonSerialize()
    {
        return [
            'header' => $this->getHeaders(),
            'data' => $this->getData()
        ];
    }

    /**
     * Add missing headers before serializing
     *
     * @throws \Exception
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
            $this->headers[self::HEADER_TIMEZONE] = (new \DateTime())->getTimezone();
        }
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
