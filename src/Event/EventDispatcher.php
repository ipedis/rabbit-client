<?php


namespace Ipedis\Rabbit\Event;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use PhpAmqpLib\Message\AMQPMessage;

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

    private function getEventName($event): string
    {
        if ($event instanceof ChannelAbstract) {
            return (string)$event;
        }

        if (ChannelFactory::matchPartial($event)) {
            return (string)$this->channelFactory->getEvent($event);
        }

        return $event;
    }
}
