<?php

namespace Ipedis\Rabbit\Event;


use AMQPEnvelope;
use AMQPQueue;
use Closure;
use Exception;
use Ipedis\Rabbit\Connector;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
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
        $this->queueDeclare();
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
     * @throws MessagePayloadFormatException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function main(AMQPEnvelope $message, AMQPQueue $q)
    {
        $messagePayload = EventMessagePayload::fromJson($message->getBody());

        try {
            if ($this->isEventOnWhitelist($messagePayload->getChannel())) {
                $this->makeMessageHandler()($messagePayload);
            }
        } catch (Exception $exception) {
            $this->makeExceptionHandler()($exception, $messagePayload);
        }

        $q->ack($message->getDeliveryTag());
    }

    /**
     * Declare Queue and bind with exchange
     */
    protected function queueDeclare()
    {
        $this->queue = new AMQPQueue($this->channel);
        $this->queue->setFlags(AMQP_EXCLUSIVE);
        $this->queue->declareQueue();
        $this->resolveRoutingKeys();
    }

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
    protected function isEventOnWhitelist(string $eventName): bool
    {
        return true;
    }
}
