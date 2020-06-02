<?php

namespace Ipedis\Demo\Rabbit\Worker\Event;


use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventListener;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

class Binding extends ConnectorAbstract implements OnBeforeMessage, OnAfterMessage
{
    use EventListener;

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
     * Process messages coming from queue
     *
     * @return Closure
     */
    protected function makeMessageHandler(): Closure
    {
        return function (EventMessagePayload $messagePayload) {
            var_dump($messagePayload->getChannel(), 'default handler');
        };
    }

    protected function onExportedPublication(EventMessagePayload $messagePayload)
    {
        var_dump($messagePayload->getChannel(), 'dedicated handler');
    }

    protected function onUpdatedPublication() : Closure
    {
        return function (EventMessagePayload $messagePayload) {
            var_dump($messagePayload->getChannel(), 'dedicated handler with Closure');
        };
    }

    /**
     * Handle errors during processing of message
     *
     * @return Closure
     */
    protected function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, ?EventMessagePayload $messagePayload) {
            printf($exception->getMessage()."\n\n");
        };
    }

    /**
     *  Accepted return value:
     * - string
     * - Array of string
     * - RouteKeyResolverInterface
     */
    protected function getBindingKey()
    {

//        return 'v1.preview.admin.publication.was-updated';
        return ['v1.admin.publication.*', 'v1.preview.publication.was-updated'];
    }

    /**
     * Optional method to have more filtering on what it will be handle by MakeMessageHandler closure.
     * @param string $eventName
     * @return bool
     */
    protected function isSubscribed(string $eventName): bool
    {
        return in_array($eventName, [
            'v1.admin.publication.was-exported',
            'v1.preview.publication.was-updated',
            'v1.admin.publication.was-deleted',
        ]);
    }

    protected function getHandledMessages(): iterable
    {
        yield 'v1.admin.publication.was-exported' => ['method' => 'onExportedPublication'];
        yield 'v1.preview.publication.was-updated' => ['method' => 'onUpdatedPublication'];
    }

    /**
     * Hook to run before worker handles message
     */
    public function beforeMessageHandled()
    {
        printf("WORKER LIFECYCLE HOOK : BEFORE HANDLING MESSAGE..."."\n\n");
    }

    /**
     * Hook to run after worker handles message
     */
    public function afterMessageHandled()
    {
        printf("WORKER LIFECYCLE HOOK : AFTER HANDLING MESSAGE..."."\n\n");
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
}
