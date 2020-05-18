<?php

namespace Ipedis\Rabbit\Event;


use AMQPEnvelope;
use AMQPQueue;
use Closure;
use Exception;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

trait EventListener
{
    use Connector;

    /**
     * @var string
     */
    protected $worker_id;

    /**
     * @var AMQPQueue $queue
     */
    protected $queue = null;

    /**
     * Instantiate event listener by
     * - Connect to rabbitMQ
     * - Create/declare queue and bind with exchange
     * - Define callback to be used for consuming message
     */
    public function execute()
    {
        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->declareQueueIfNecessary();
        $this->queueConsume();

        $this->disconnect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function main(AMQPEnvelope $message, AMQPQueue $q)
    {
        try {
            /**
             * We have before message hook to run
             */
            if ( $this instanceOf OnBeforeMessage) $this->beforeMessageHandled();

            $this->consumeReceivedMessage($message);

            /**
             * We have after message hook to run
             */
            if ( $this instanceOf OnAfterMessage) $this->afterMessageHandled();
        } catch (\Exception $exception) {
            /**
             * Handle exception from the hooks
             */
            $this->handleException($exception);
        }

        $q->ack($message->getDeliveryTag());
    }

    /**
     * Consume the received message
     *
     * Message will be handled ONLY if
     * listener is subscribed to the event
     *
     * @param AMQPEnvelope $message
     * @throws MessagePayloadFormatException
     */
    private function consumeReceivedMessage(AMQPEnvelope $message)
    {
        $messagePayload = EventMessagePayload::fromJson($message->getBody());

        try {
            if ($this->isSubscribed($messagePayload->getChannel())) {
                $this->handleReceivedMessage($messagePayload);
            }
        } catch (Exception $exception) {
            $this->handleException($exception, $messagePayload);
        }
    }

    /**
     * Handle the message by calling dedicated callback handler
     * or the general callback handler
     *
     * @param EventMessagePayload $message
     */
    private function handleReceivedMessage(EventMessagePayload $message)
    {
        $wasCalled = false;
        foreach ($this->getHandledMessages() as $channelName => $handledMessage) {
            if (
                !empty($handledMessage['method']) &&
                $message->getChannel() === $channelName
            ) {
                $this->callHandler($handledMessage, $message);
                $wasCalled = true;
            }
        }

        // If nobody was call, fallback to makeMessageHandler
        if(!$wasCalled) $this->callHandler(['method' => 'makeMessageHandler'], $message);
    }

    /**
     * Handle exception by calling
     * client exception callback
     *
     * @param $exception
     * @param EventMessagePayload|null $messagePayload
     */
    private function handleException($exception, ?EventMessagePayload $messagePayload = null)
    {
        try {
            $this->makeExceptionHandler()($exception, $messagePayload);
        } catch (\Exception $exception) {
            $this->logException($exception);
        }
    }

    /**
     * Helper to execute proper callback
     *
     * @param array $handler
     * @param EventMessagePayload $message
     */
    private function callHandler(array $handler, EventMessagePayload $message)
    {
        $result = $this->{$handler['method']}($message);
        if(is_callable($result)) {
            $result($message);
        }
    }

    /**
     * Declare Queue and bind with exchange
     */
    protected function declareQueueIfNecessary()
    {
        $this->queue = new AMQPQueue($this->channel);
        $this->queue->setFlags(AMQP_EXCLUSIVE);
        $this->queue->declareQueue();
        $this->resolveRoutingKeys();
    }

    /**
     * Bind listener to multiple events
     *
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    private function resolveRoutingKeys()
    {
        $routingKey = $this->getBindingKey();

        // If is string, we cast it to array.
        if(is_string($routingKey)) $routingKey = [$routingKey];

        if(is_array($routingKey)) {
            foreach ($routingKey as $key) {
                if(is_string($key)) {
                    $this->queue->bind($this->exchange->getName(), $key);
                }
            }
        }
    }

    /**
     * Define callback to be executed
     * when consuming message from queue
     */
    protected function queueConsume()
    {
        $this->queue->consume([$this, 'main']);
    }

    abstract protected function makeMessageHandler(): Closure;
    abstract protected function makeExceptionHandler(): Closure;
    abstract protected function getBindingKey();

    /**
     * If you want to limit the call of callback for each message, you can filter by white list here.
     * @param string $eventName
     * @return bool
     */
    protected function isSubscribed(string $eventName): bool
    {
        return true;
    }

    /**
     * By default there is no dedicated handler
     */
    protected function getHandledMessages(): iterable
    {
        return [];
    }

    /**
     * Prototype method
     * Child can overide this function to log exceptions
     *
     * @param Exception $exception
     */
    protected function logException(\Exception $exception){}
}
