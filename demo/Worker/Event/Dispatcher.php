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

    const EVENTS_TO_DISPATCH = [
        'v1.admin.publication.was-created',
        'v1.admin.publication.was-exported',
        'v1.preview.publication.was-updated',
        'v1.admin.publication.was-deleted'
    ];

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
        foreach (self::EVENTS_TO_DISPATCH as $eventType) {
            // we construct the event message by providing channel and payload.
            $eventType = EventMessagePayload::build(
                EventChannel::fromString($eventType),
                [
                    'publication' => [
                        'sid' => 1
                    ]
                ]
            );

            // now we can dispatch the event through our message broker.
            $this->dispatch($eventType);
        }
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


    public function getQueuePrefix(): string
    {
        return 'demo.event';
    }
}
