<?php

namespace Ipedis\Demo\Rabbit\Worker\Order;

use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Demo\Rabbit\Worker\Handler\ManagerHandler;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\Exception\RabbitClientConnectException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use Ipedis\Rabbit\Order\Manager as ManagerTrait;

class Manager extends ConnectorAbstract
{
    use ManagerTrait;

    public const IS_VERBOSE = true;

    /**
     * @var ChannelFactory $channelFactory
     */
    private ChannelFactory $channelFactory;

    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $messagePayloadValidator;

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
     * @throws RabbitClientConnectException
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

    public function __destruct()
    {
        $this->disconnect();
    }

    public function main()
    {
        /**
         * Example of binding a handler to an event
         */
        $messageHandler = new ManagerHandler(10);

        /**
         * We publish N Tasks on queue "Worker" who should be consume by this Worker.
         * We give also Anonymous callback Queue to have feedback from worker.
         */
        for ($i = 0; $i < $messageHandler->getNumberTask(); $i++) {

            /**
             * Create Order payload
             */
            $orderMessagePayload = OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.generate'), [
                "hasToFail" => $i % 2 === 0, // Simulate failure on each pair message.
                "name"      => "task {$i}"
            ]);

            $this->publish($orderMessagePayload)
                ->bind(MessageHandlerInterface::TYPE_ERROR, function (ReplyMessagePayload $message, Error $error) {
//                    var_dump($error);
                })
            ;

            if (self::IS_VERBOSE) {
                $this->bind(MessageHandlerInterface::TYPE_PROGRESS, function (ReplyMessagePayload $message) {
                    // pro tips: if you want to find only the reply, you can use $message->getReply().
                    printf("[[ ----------- PROGRESSION PERCENTAGE %s%% COMPLETED ---------- ]]\n", (string) $this->getPercentageProgression());
                });
            }
        }

        if (self::IS_VERBOSE) {
            printf("%s messages are published on queue\n\n", count($this->getDispatchedOrders()));
        }

        $this->run();

        if (self::IS_VERBOSE) {
            printf(
                "All orders(%s in total) executed with %s orders as success and %s orders as error :). \n",
                count($this->getCompletedOrders()),
                count($this->getSuccessfulOrders()),
                count($this->getFailedOrders())
            );
        }
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
        return 'demo.order';
    }
}
