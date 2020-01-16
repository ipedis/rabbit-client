<?php


namespace Ipedis\Rabbit\Event;


use Ipedis\Rabbit\Connector;
use PhpAmqpLib\Message\AMQPMessage;

trait EventDispatcher
{
    use Connector;

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @param string $event
     * @param array $data
     */
    public function dispatchEvent(string $event, array $data)
    {
        /**
         * todo : add schema validation, protocol version.
         */
        $msg = new AMQPMessage(json_encode(['event' => $event, 'data' => $data]));
        $this->channel->basic_publish($msg,$this->getExchangeName(), $event);
    }
}