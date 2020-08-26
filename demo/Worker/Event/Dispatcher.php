<?php

namespace Ipedis\Demo\Rabbit\Worker\Event;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventDispatcher;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

class Dispatcher extends ConnectorAbstract
{
    use EventDispatcher;

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

    public function main()
    {
        $this->dispatch(EventMessagePayload::build(
            EventChannel::fromString('v1.admin.publication.was-created'),
            [
                'publication' => [
                    'sid' => 1
                ]
            ]
        ));

        $this->dispatch(EventMessagePayload::build(
            EventChannel::fromString('v1.admin.publication.was-exported'),
            [
                'publication' => [
                    'sid' => 1
                ]
            ]
        ));

        $this->dispatch(EventMessagePayload::build(
            EventChannel::fromString('v1.preview.publication.was-updated'),
            [
                'publication' => [
                    'sid' => 2
                ]
            ]
        ));

        $this->dispatch(EventMessagePayload::build(
            EventChannel::fromString('v1.admin.publication.was-deleted'),
            [
                'publication' => [
                    'sid' => 3
                ]
            ]
        ));
    }

    /**
     * @return ChannelFactory
     */
    public function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }

    /**
     * @return ValidatorInterface
     */
    public function getMessagePayloadValidator(): ValidatorInterface
    {
        return $this->messagePayloadValidator;
    }

    /**
     * Url of recovery project
     *
     * @return string
     */
    public function getRecoveryEventStoreEndpoint(): string
    {
        return 'http://recovery.publispeak.local/api/event/store';
    }

    public function getSignatureKey(): string
    {
        return 'ThisIsNotASecretKey';
    }
}
