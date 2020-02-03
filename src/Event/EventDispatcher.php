<?php


namespace Ipedis\Rabbit\Event;


use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Trait EventDispatcher
 * @package Ipedis\Rabbit\Event
 * @method ChannelFactory|null matchPartial($channel)
 */
trait EventDispatcher
{
    use Connector;

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @param mixed $event
     * @param array $data
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     */
    public function dispatchEvent($event, array $data)
    {
        if( $this->channel === null) {
            $this->connect();
        }

        if (!isset($this->channelFactory) || !$this->channelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        $eventName = $this->getEventName($event);

        /**
         * todo : add schema validation, protocol version.
         */
        $msg = new AMQPMessage(json_encode(['event' => $eventName, 'data' => $data]));
        $this->channel->basic_publish($msg, $this->getExchangeName(), $eventName);
    }

    /**
     * @param $event
     * @return string
     * @throws ChannelNamingException
     */
    private function getEventName($event): string
    {
        if (is_string($event)) {
            // if it is partial channel name
            if ($this->channelFactory->matchPartial($event)) {
                return (string)$this->channelFactory->getEvent($event);
            }

            // if it is full name, this will throw exception if full name is invalid.
            $eventObj = EventChannel::fromString($event);

            return (string)$eventObj;
        }
        // if it is an instance, get channel full name
        if ($event instanceof EventChannel) {
            return (string)$event;
        }

        // no criteria fulfilled, throw an exception.
        throw new ChannelNamingException('Invalid channel provided.');
    }
}
