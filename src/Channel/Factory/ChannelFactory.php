<?php

namespace Ipedis\Rabbit\Channel\Factory;

use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

class ChannelFactory
{
    private const TYPE_EVENT = 'event';
    private const TYPE_ORDER = 'order';

    /**
     * @var string
     */
    private $protocolVersion;

    /**
     * @var string
     */
    private $serviceName;

    public function __construct(
        string $protocolVersion,
        string $serviceName
    )
    {
        $this->protocolVersion = $protocolVersion;
        $this->serviceName = $serviceName;
    }

    /**
     * @param string $partialChannel
     * @param string $protocolVersion
     * @return EventChannel
     * @throws ChannelNamingException
     */
    public function getEvent(string $partialChannel, string $protocolVersion = null): EventChannel
    {
        preg_match(EventChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);

        if (empty($matched['aggregate'] || $matched['action'])) {
            throw new ChannelNamingException('impossible to parse : ' . $partialChannel);
        }

        /** @var EventChannel */
        return $this->getChannel(self::TYPE_EVENT, $matched['aggregate'], $matched['action'], $protocolVersion);
    }

    /**
     * @param string $type
     * @param string $aggregate
     * @param string $action
     * @param string|null $protocolVersion
     * @return ChannelAbstract
     */
    private function getChannel(
        string $type,
        string $aggregate,
        string $action,
        string $protocolVersion = null
    ): ChannelAbstract
    {
        switch ($type) {
            case self::TYPE_EVENT:
                return EventChannel::build($protocolVersion ?? $this->protocolVersion, $this->serviceName, $aggregate, $action);
                break;
            case self::TYPE_ORDER:
                return OrderChannel::build($protocolVersion ?? $this->protocolVersion, $this->serviceName, $aggregate, $action);
                break;
        }
    }

    /**
     * @param string $partialChannel
     * @param string $protocolVersion
     * @return OrderChannel
     * @throws ChannelNamingException
     */
    public function getOrder(string $partialChannel, string $protocolVersion = null): OrderChannel
    {
        preg_match(OrderChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);

        if (empty($matched['aggregate'] || $matched['action'])) {
            throw new ChannelNamingException('impossible to parse : ' . $partialChannel);
        }

        /** @var OrderChannel */
        return $this->getChannel(self::TYPE_ORDER, $matched['aggregate'], $matched['action'], $protocolVersion);
    }

    /**
     * @param string $channel
     * @return bool
     */
    public function matchPartial(string $channel): bool
    {
        preg_match(OrderChannel::PARTIAL_CHANNEL_PATTERN, $channel, $matches);

        return !empty($matches['aggregate']) &&
            !empty($matches['action']);
    }

    /**
     * @param string $channel
     * @return bool
     */
    public function match(string $channel): bool
    {
        preg_match(OrderChannel::CHANNEL_PATTERN, $channel, $matches);

        return !empty($matches['protocol']) &&
            !empty($matches['service']) &&
            !empty($matches['aggregate']) &&
            !empty($matches['action']);
    }
}
