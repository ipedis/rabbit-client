<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Channel\Factory;

use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

class ChannelFactory
{
    private const TYPE_EVENT = 'event';

    private const TYPE_ORDER = 'order';

    public function __construct(private readonly string $protocolVersion, private readonly string $serviceName)
    {
    }

    /**
     * @throws ChannelNamingException
     */
    public function getEvent(string $partialChannel, ?string $protocolVersion = null): EventChannel
    {
        preg_match(EventChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);

        if (!isset($matched['aggregate']) || !isset($matched['action']) || $matched['aggregate'] === '' || $matched['action'] === '') {
            throw new ChannelNamingException('impossible to parse : ' . $partialChannel);
        }

        /** @var EventChannel */
        return $this->getChannel(self::TYPE_EVENT, $matched['aggregate'], $matched['action'], $protocolVersion);
    }

    private function getChannel(
        string $type,
        string $aggregate,
        string $action,
        ?string $protocolVersion = null
    ): ChannelAbstract {
        return match ($type) {
            self::TYPE_EVENT => EventChannel::build($protocolVersion ?? $this->protocolVersion, $this->serviceName, $aggregate, $action),
            self::TYPE_ORDER => OrderChannel::build($protocolVersion ?? $this->protocolVersion, $this->serviceName, $aggregate, $action),
            default => throw new \InvalidArgumentException('Unknown channel type: ' . $type),
        };
    }

    /**
     * @throws ChannelNamingException
     */
    public function getOrder(string $partialChannel, ?string $protocolVersion = null): OrderChannel
    {
        preg_match(OrderChannel::PARTIAL_CHANNEL_PATTERN, $partialChannel, $matched);

        if (!isset($matched['aggregate']) || !isset($matched['action']) || $matched['aggregate'] === '' || $matched['action'] === '') {
            throw new ChannelNamingException('impossible to parse : ' . $partialChannel);
        }

        /** @var OrderChannel */
        return $this->getChannel(self::TYPE_ORDER, $matched['aggregate'], $matched['action'], $protocolVersion);
    }

    public function matchPartial(string $channel): bool
    {
        preg_match(OrderChannel::PARTIAL_CHANNEL_PATTERN, $channel, $matches);

        return isset($matches['aggregate']) && $matches['aggregate'] !== '' &&
            isset($matches['action']) && $matches['action'] !== '';
    }

    public function match(string $channel): bool
    {
        preg_match(OrderChannel::CHANNEL_PATTERN, $channel, $matches);

        return isset($matches['protocol']) && $matches['protocol'] !== '' &&
            isset($matches['service']) && $matches['service'] !== '' &&
            isset($matches['aggregate']) && $matches['aggregate'] !== '' &&
            isset($matches['action']) && $matches['action'] !== '';
    }
}
