<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Channel;

use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

abstract class ChannelAbstract implements \Stringable
{
    /**
     * <protocol>.<service>.<aggregate>.<action>
     */
    public const CHANNEL_PATTERN = '#^(?<protocol>v\d+)\.(?<service>[a-z-]+)\.(?<aggregate>[a-z-]+(?:\.[a-z-]+)?)\.(?<action>[a-z-]+)$#';

    /**
     * <aggregate>.<action>
     */
    public const PARTIAL_CHANNEL_PATTERN = '#^(?<aggregate>[a-z-]+(?:\.[a-z-]+)?)\.(?<action>[a-z-]+)$#';

    private readonly string $protocol;

    private readonly string $service;

    private readonly string $aggregate;

    private readonly string $action;

    /**
     * @throws ChannelNamingException
     */
    protected function __construct(string $protocol, string $service, string $aggregate, string $action)
    {
        $this->assertProtocol($protocol);
        $this->assertService($service);
        $this->assertAggregate($aggregate);
        $this->assertAction($action);

        $this->protocol = $protocol;
        $this->service = $service;
        $this->aggregate = $aggregate;
        $this->action = $action;
    }

    private function assertProtocol(string $protocol): void
    {
        if (!preg_match('#^v\d+$#', $protocol)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid protocol.', $protocol));
        }
    }

    private function assertService(string $service): void
    {
        if (!preg_match('#^[\w-]+$#', $service)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid service.', $service));
        }
    }

    private function assertAggregate(string $aggregate): void
    {
        if (!preg_match('#^[\w-]+(?:\.[\w-]+)?$#', $aggregate)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid aggregate.', $aggregate));
        }
    }

    private function assertAction(string $action): void
    {
        if (!preg_match('#^[\w-]+$#', $action)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid action.', $action));
        }
    }

    /**
     * @return static
     * @throws ChannelNamingException
     */
    public static function fromString(string $channel): self
    {
        $catched = self::assertChannel($channel);

        return new static(
            $catched['protocol'],
            $catched['service'],
            $catched['aggregate'],
            $catched['action']
        );
    }

    /**
     * @throws ChannelNamingException
     */
    private static function assertChannel(string $channel): array
    {
        preg_match(self::CHANNEL_PATTERN, $channel, $catched);
        if (
            empty($catched['protocol']) ||
            empty($catched['service']) ||
            empty($catched['aggregate']) ||
            empty($catched['action'])
        ) {
            throw new ChannelNamingException("Channel can't be parsed.");
        }

        return $catched;
    }

    /**
     * @throws ChannelNamingException
     */
    public static function getTypeFromChannelName(string $channel): string
    {
        $channelDetails = self::assertChannel($channel);

        return sprintf('%s.%s.%s', $channelDetails['service'], $channelDetails['aggregate'], $channelDetails['action']);
    }

    /**
     * @return static
     */
    public static function build(string $protocol, string $service, string $aggregate, string $action): self
    {
        return new static(
            $protocol,
            $service,
            $aggregate,
            $action
        );
    }

    public function __toString(): string
    {
        return sprintf('%s.%s.%s.%s', $this->getProtocol(), $this->getService(), $this->getAggregate(), $this->getAction());
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getAggregate(): string
    {
        return $this->aggregate;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
