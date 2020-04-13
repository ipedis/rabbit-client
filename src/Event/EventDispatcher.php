<?php

namespace Ipedis\Rabbit\Event;


use GuzzleHttp\Client;
use Ipedis\Rabbit\Channel\EventChannel;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Channel\ChannelNamingException;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadValidatorException;
use Ipedis\Rabbit\Exception\RabbitClientConnectException;
use Ipedis\Rabbit\Exception\RabbitClientPublishException;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use Ipedis\Rabbit\Signer\RequestSigner;

/**
 * Trait EventDispatcher
 *
 * @package Ipedis\Rabbit\Event
 * @method ChannelFactory|null matchPartial($channel)
 */
trait EventDispatcher
{
    use Connector;
    use RequestSigner;

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Publish event on exchange
     *
     * @param EventMessagePayload $messagePayload
     *
     * @throws ChannelFactoryException
     * @throws ChannelNamingException
     * @throws MessagePayloadValidatorException
     */
    public function dispatch(EventMessagePayload $messagePayload)
    {
        try {
            if ( $this->channel === null) {
                $this->connect();
            }

            if (!$this->getChannelFactory() instanceof ChannelFactory) {
                throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
            }

            if (!$this->getMessagePayloadValidator() instanceof ValidatorInterface) {
                throw new MessagePayloadValidatorException("Must provide message payload validator {messagePayloadValidator}");
            }

            /**
             * Validate channel naming and return event name
             */
            $eventName = $this->getEventName($messagePayload->getChannel());

            /**
             * Validate message payload data schema
             */
            $this->getMessagePayloadValidator()->validate($messagePayload);

            /**
             * Publish message on exchange
             */
            $this->publishToExchange(json_encode($messagePayload), $eventName);
        } catch (RabbitClientConnectException | RabbitClientPublishException $exception) {
            /**
             * Error occured while connecting to RabbitMQ OR
             * publishing to exchange : event is stored on recovery
             */
            $this->storeEventOnRecovery($messagePayload);
        }
    }

    /**
     * Validate event naming
     * Event name is used as routing key when publishing message
     *
     * @param $event
     * @return string
     * @throws ChannelNamingException
     */
    private function getEventName($event): string
    {
        if (is_string($event)) {
            // if it is partial channel name
            if ($this->getChannelFactory()->matchPartial($event)) {
                return (string)$this->getChannelFactory()->getEvent($event);
            }

            // if it is full name, this will throw exception if full name is invalid.
            $eventObj = EventChannel::fromString($event);

            return (string)$eventObj;
        }
        // if it is an instance, get channel full name
        if ($event instanceof EventChannel) {
            return (string)$event;
        }

        // no criteria fulfilled, throw an exception.
        throw new ChannelNamingException('Invalid channel provided.');
    }

    /**
     * Store event on recovery
     *
     * @param EventMessagePayload $payload
     */
    private function storeEventOnRecovery(EventMessagePayload $payload)
    {
        $this->getClientWithSignHandler()
            ->post($this->getRecoveryEventStoreEndpoint(), [
                'body' => json_encode($payload),
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ])
        ;
    }
    abstract public function getRecoveryEventStoreEndpoint(): string;
}
