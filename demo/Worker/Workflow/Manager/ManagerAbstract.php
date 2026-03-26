<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use Ipedis\Rabbit\Workflow\Manager;
use Ipedis\Rabbit\Exception\RabbitClientConnectException;

abstract class ManagerAbstract extends ConnectorAbstract
{
    use Manager;

    /**
     * Manager constructor.
     * @throws RabbitClientConnectException
     */
    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $exchange,
        string $type,
        protected ChannelFactory $channelFactory,
        protected ValidatorInterface $messagePayloadValidator
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->connect();

        /**
         * Initialise order queue
         */
        $this->resetOrdersQueue();
    }

    protected function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }

    protected function getMessagePayloadValidator(): ValidatorInterface
    {
        return $this->messagePayloadValidator;
    }

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }
}
