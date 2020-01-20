<?php


namespace Ipedis\Rabbit\Channel\Factory;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

class ChannelFactory
{
    /**
     * @var string
     */
    private $protocolVersion;
    /**
     * @var string
     */
    private $serviceName;


    private const TYPE_EVENT = 'event';
    private const TYPE_ORDER = 'order';

    public function __construct(
        string $protocolVersion,
        string $serviceName
    ) {
        $this->protocolVersion = $protocolVersion;
        $this->serviceName = $serviceName;
    }

    /**
     * @param string $partialChannel
     * @return EventChannel
     * @throws ChannelNamingException
     */
    public function getEvent(string $partialChannel): EventChannel
    {
        preg_match(EventChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);
        if (empty($matched['aggregate'] || $matched['action'])) throw new ChannelNamingException('impossible to parse : '.$partialChannel);
        /** @var EventChannel */
        return $this->getChannel(self::TYPE_EVENT, $matched['aggregate'], $matched['action']);
    }

    /**
     * @param string $partialChannel
     * @return OrderChannel
     * @throws ChannelNamingException
     */
    public function getOrder(string $partialChannel): OrderChannel
    {
        preg_match(OrderChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);
        if (empty($matched['aggregate'] || $matched['action'])) throw new ChannelNamingException('impossible to parse : '.$partialChannel);
        /** @var OrderChannel */
        return $this->getChannel(self::TYPE_ORDER, $matched['aggregate'], $matched['action']);
    }

    /**
     * @param string $type
     * @param string $aggregate
     * @param string $action
     * @return ChannelAbstract
     */
    private function getChannel(
        string $type,
        string $aggregate,
        string $action
    ): ChannelAbstract {
        switch ($type)
        {
            case self::TYPE_EVENT:
                return EventChannel::build($this->protocolVersion, $this->serviceName, $aggregate, $action);
            break;
            case self::TYPE_ORDER:
                return OrderChannel::build($this->protocolVersion, $this->serviceName, $aggregate, $action);
            break;
        }
    }
}
