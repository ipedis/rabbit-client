<?php

namespace Ipedis\Demo\Rabbit\Utils;


use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

abstract class WorkerAbstract extends ConnectorAbstract
{
    /**
     * @var ChannelFactory $channelFactory
     */
    private $channelFactory;

    /**
     * @var ValidatorInterface $messagePayloadValidator
     */
    private $messagePayloadValidator;

    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $exchange,
        string $type,
        ChannelFactory $channelFactory,
        ValidatorInterface $messagePayloadValidator
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->channelFactory = $channelFactory;
        $this->messagePayloadValidator = $messagePayloadValidator;
    }

    /**
     * @return ChannelFactory
     */
    protected function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }

    /**
     * @return ValidatorInterface
     */
    protected function getMessagePayloadValidator(): ValidatorInterface
    {
        return $this->messagePayloadValidator;
    }
}
