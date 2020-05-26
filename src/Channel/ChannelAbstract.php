<?php


namespace Ipedis\Rabbit\Channel;


use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;

abstract class ChannelAbstract
{
    /**
     * <protocol>.<service>.<aggregate>.<action>
     */
    private const CHANNEL_PATTERN = '#^(?<protocol>v\d+)\.(?<service>[\w-]+)\.(?<aggregate>[\w-]+(?:\.[\w-]+)?)\.(?<action>[\w-]+)$#';
    /**
     * <aggregate>.<action>
     */
    const PARTIAL_CHANNEL_PATTERN = '#^(?<aggregate>[\w-]+(?:\.[\w-]+)?)\.(?<action>[\w-]+)$#';

    /**
     * @var string
     */
    private $protocol;
    /**
     * @var string
     */
    private $service;
    /**
     * @var string
     */
    private $aggregate;
    /**
     * @var string
     */
    private $action;

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

    /**
     * @param string $channel
     * @return array
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
            throw new ChannelNamingException('Channel can\'t be parsed.');
        }
        return $catched;
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * @return string
     */
    public function getAggregate(): string
    {
        return $this->aggregate;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    public function __toString()
    {
        return "{$this->getProtocol()}.{$this->getService()}.{$this->getAggregate()}.{$this->getAction()}";
    }

    /**
     * @param string $channel
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

    public static function getTypeFromString(string $channel) : string
    {
        $channelDetails = self::assertChannel($channel);

        return sprintf('%s.%s.%s', $channelDetails['service'], $channelDetails['aggregate'], $channelDetails['action']);
    }

    /**
     * @param string $protocol
     * @param string $service
     * @param string $aggregate
     * @param string $action
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


    private function assertProtocol(string $protocol)
    {
        if(!preg_match('#^v\d+$#', $protocol)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid protocol.', $protocol));
        }
    }

    private function assertService(string $service)
    {
        if(!preg_match('#^[\w-]+$#', $service)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid service.', $service));
        }
    }

    private function assertAggregate(string $aggregate)
    {
        if(!preg_match('#^[\w-]+(?:\.[\w-]+)?$#', $aggregate)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid aggregate.', $aggregate));
        }
    }

    private function assertAction(string $action)
    {
        if(!preg_match('#^[\w-]+$#', $action)) {
            throw new ChannelNamingException(sprintf('"%s" is not valid action.', $action));
        }
    }
}
