<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use Ipedis\Rabbit\Workflow\Manager;

abstract class ManagerAbstract extends ConnectorAbstract
{
    use Manager;

    /**
     * @var ChannelFactory $channelFactory
     */
    protected $channelFactory;

    /**
     * @var ValidatorInterface
     */
    protected $messagePayloadValidator;

    /**
     * Manager constructor.
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $exchange
     * @param string $type
     * @param ChannelFactory $channelFactory
     * @param ValidatorInterface $messagePayloadValidator
     * @throws \Ipedis\Rabbit\Exception\RabbitClientConnectException
     */
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
        $this->connect();

        /**
         * Initialise order queue
         */
        $this->resetOrdersQueue();
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

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }
}
