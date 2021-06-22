<?php

namespace Ipedis\Rabbit\Order;

use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use Closure;
use Exception;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Channel\ChannelFactoryException;
use Ipedis\Rabbit\Exception\Helper\Context;
use Ipedis\Rabbit\Exception\Helper\Serializer;
use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

/**
 * Class Worker
 * @package Ipedis\Rabbit
 */
trait Worker
{
    /**
     * @var string $worker_id
     */
    protected string $worker_id;

    /**
     * @var AMQPQueue $queue
     */
    protected AMQPQueue $queue;

    /**
     * @var Context
     */
    protected Context $context;

    /**
     * Method to initialise worker by
     * - connecting to rabbitMQ
     * - declare the queue
     * - declare callback to be used to consume message
     */
    public function execute()
    {
        /**
         * Before stating worker
         * Check if channel factory is defined
         * to validate event naming
         */
        if (!$this->getChannelFactory() instanceof ChannelFactory) {
            throw new ChannelFactoryException('Must provide channel factory {channelFactory} with version and service.');
        }

        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->queueDeclare();
        $this->queueConsume();
        $this->disconnect();
    }

    abstract protected function getChannelFactory();

    /**
     * Create new queue on rabbitMQ and
     * bind queue to exchange with routing key
     */
    protected function queueDeclare()
    {
        $queueName = $this->getRoutingKeyWithPrefix($this->getQueueName());

        $this->queue = new AMQPQueue($this->channel);
        $this->queue->setFlags(AMQP_DURABLE);
        $this->queue->setName($queueName);
        $this->queue->declareQueue();
        $this->queue->bind($this->getExchangeName(), $queueName);
    }

    /**
     * Can be string or array of keys
     *
     * @return mixed
     */
    abstract protected function getQueueName();

    /**
     * The exchange to be used to bind worker's queue
     *
     * @return string
     */
    abstract protected function getExchangeName(): string;

    /**
     * Attach callback to queue
     *
     */
    protected function queueConsume()
    {
        $this->queue->consume([$this, "main"]); //$this->mainWork(AMQPMessage $req) callback
    }

    public function __destruct()
    {
        if ($this->queue) {
            $this->queue->delete();
        }
        $this->disconnect();
    }

    /**
     * Method first executed when receiving message
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     */
    public function main(AMQPEnvelope $message, AMQPQueue $q)
    {
        try {
            /**
             * We reset the context bag for each consumed message.
             */
            $this->context = Context::initialize();
            /**
             * We have before message hook to run
             */
            if ($this instanceof OnBeforeMessage) {
                $this->beforeMessageHandled();
            }

            $this->consumeReceivedMessage($message, $q);

            /**
             * We have after message hook to run
             */
            if ($this instanceof OnAfterMessage) {
                $this->afterMessageHandled();
            }
        } catch (Exception $exception) {
            /**
             * Handle exception from hook and
             * message payload creation
             */
            $this->handleException($exception);
        } finally {
            /**
             * to prevent future memory leak due to object reference inside context.
             *
             */
            $this->context = Context::initialize();
        }
    }

    /**
     * Consume message by calling client callback and
     * standardize the reply to manager
     *
     * - Notify status success if callback run successfully
     * - Notify status error if error captured while running client callback
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @throws MessagePayloadFormatException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    private function consumeReceivedMessage(AMQPEnvelope $message, AMQPQueue $q)
    {
        /**
         * Get current moment
         */
        $startAt = $this->getCurrentMoment();

        /**
         * Ignore if message not proper json
         */
        if (!$this->isValidMessageFormat($message->getBody())) {
            return;
        }

        /**
         * Re-construct message payload objectValue from request body
         */
        $messagePayload = OrderMessagePayload::fromJson($message->getBody());
        /** add original message on the context */
        $this->context->add('from', $messagePayload);

        try {
            /**
             * 1. Validate channel naming and return event name
             */
            if (!$this->isValidChannelName($messagePayload->getChannel())) {
                return;
            }


            /**
             * Notify manager of start consuming & task status change
             */
            $this->notifyTo($message, ReplyMessagePayload::buildFromOrderMessagePayload(
                $messagePayload,
                MessageHandlerInterface::TYPE_STARTING,
                []
            ));

            $answer = $this->makeMessageHandler()($message, $messagePayload);
            if (is_callable($answer)) {
                $answer = $answer($message, $messagePayload);
            }
            // force status to success.
            $answer['status'] = MessageHandlerInterface::TYPE_SUCCESS;
        } catch (Exception $exception) {
            $context = $this->handleException($exception, $messagePayload);
            if ($context instanceof Context) {
                $this->context = $context;
            } elseif (is_array($context)) {
                foreach ($context as $index => $item) {
                    $this->context->add($index, $item);
                }
            }

            $answer = [
                'worker' => self::class,
                'id' => $this->worker_id,
                'status' => MessageHandlerInterface::TYPE_ERROR,
                'correlation_id' => $messagePayload->getOrderId(),
                /** todo remove message and code as is duplicated from error */
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'error' => Serializer::fromException($exception, $this->context)
            ];
        } finally {
            /**
             * Create final message to reply back to manager with
             * same taskId (correlation id)
             */
            $replyToMessage = ReplyMessagePayload::buildFromOrderMessagePayload(
                $messagePayload,
                $answer['status'],
                $answer,
                ['executionTime' => ($this->getCurrentMoment() - $startAt)]
            );

            /**
             * We always consider current message as consumed
             * let the responsibility of the manager to determine if
             * message have to be republished or not.
             */
            $this->replyTo($message, $q, $replyToMessage);
        }
    }

    /**
     * get current timer
     */
    private function getCurrentMoment(): float
    {
        return microtime(true);
    }

    /**
     * Check if message is valid json
     *
     * @param $message
     * @return bool
     */
    private function isValidMessageFormat(string $message): bool
    {
        return json_decode($message) != null;
    }

    /**
     * Check if channel name follows proper naming
     *
     * @param string $channelName
     * @return bool
     */
    private function isValidChannelName(string $channelName): bool
    {
        return $this->getChannelFactory()->match($channelName);
    }

    /**
     * Notify manager with an update
     * Can be a progress update or the final reply back message
     *
     * @param AMQPEnvelope $message
     * @param ReplyMessagePayload $replyToMessagePayload
     * @return void
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function notifyTo(AMQPEnvelope $message, ReplyMessagePayload $replyToMessagePayload)
    {
        $defaultExchange = new AMQPExchange($this->channel);

        /**
         * Publishing to the same channel from the incoming message
         */
        $defaultExchange->publish(
            json_encode($replyToMessagePayload),
            $message->getReplyTo(),
            AMQP_NOPARAM,
            $replyToMessagePayload->getMessageProperties()
        );
    }

    /**
     * The client callback to be executed
     * on receiving a message
     *
     * @return Closure | array
     */
    abstract protected function makeMessageHandler();

    /**
     * Handle exception by calling
     * client exception callback
     *
     * @param $exception
     * @param OrderMessagePayload|null $messagePayload
     * @return array
     */
    private function handleException($exception, ?OrderMessagePayload $messagePayload = null)
    {
        try {
            $context = $this->makeExceptionHandler()($exception, $messagePayload);
        } catch (Exception $exception) {
            $this->logException($exception);
            $context = [];
        } finally {
            return $context;
        }
    }

    /**
     * The client callback to be executed
     * if there is an exception while handling a message
     *
     * @return Closure
     */
    abstract protected function makeExceptionHandler(): Closure;

    /**
     * Prototype method
     * Child can overide this function to log exceptions
     *
     * @param Exception $exception
     */
    protected function logException(Exception $exception)
    {
    }

    /**
     * Notify manager with reply message and
     * acknowledge message as processed on rabbitMQ
     *
     * @param AMQPEnvelope $message
     * @param AMQPQueue $q
     * @param ReplyMessagePayload $replyToMessagePayload
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    public function replyTo(AMQPEnvelope $message, AMQPQueue $q, ReplyMessagePayload $replyToMessagePayload)
    {
        /**
         * Notify manager with reply
         */
        $this->notifyTo($message, $replyToMessagePayload);

        /*
         * Acknowledging the message
         */
        $q->ack($message->getDeliveryTag());
    }
}
