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
    public function __construct(string $channel, array $data = [], array $headers = [])
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->channel = $channel;

        $this->headers[self::HEADER_CHANNEL] = $channel;
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

    public function jsonSerialize()
    {
        $this->checkForMissingHeaders();

        return [
            'header' => $this->getHeaders(),
            'data'   => $this->getData()
        ];
    }

    /**
     * Add missing headers before serializing
     *
     * @throws \Exception
     */
    private function checkForMissingHeaders(): void
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
