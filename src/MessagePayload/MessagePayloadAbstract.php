<?php

namespace Ipedis\Rabbit\MessagePayload;


/**
 * This class is responsible for standardizing the message body
 *
 */
abstract class MessagePayloadAbstract implements MessagePayloadInterface
{
    const HEADER_UUID       = 'uuid';
    const HEADER_TIMESTAMP  = 'sendAt';
    const HEADER_TIMEZONE   = 'timezone';
    const HEADER_CHANNEL    = 'channel';

    /**
     * data storage
     *
     * @var array
     */
    protected $data;

    /**
     * header storage
     *
     * @var array
     */
    protected $headers;

    /**
     * Channel for the message
     *
     * @var string
     */
    protected $channel;

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
        $this->addHeader(self::HEADER_CHANNEL, $channel);
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
     * @param string $key
     * @return bool
     */
    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
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
    public function getHeaders(): array
    {
        return $this->headers;
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
            $default
        ;
    }

    /**
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getUuid(): string
    {
        return $this->getHeader(self::HEADER_UUID);
    }

    public function getTimestamp(): int
    {
        return $this->getHeader(self::HEADER_TIMESTAMP);
    }

    public function getTimezone() : array
    {
        return $this->getHeader(self::HEADER_TIMEZONE);
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

    public function jsonSerialize()
    {
        $this->setDefaultHeader();

        return [
            'header' => $this->getHeaders(),
            'data'   => $this->getData()
        ];
    }

    /**
     * @param string $key
     * @param $value
     * @return MessagePayloadAbstract
     */
    protected function addHeader(string $key, $value) :self
    {
        $this->headers[$key] = $value;

        return $this;
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
}
