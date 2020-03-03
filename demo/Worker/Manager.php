<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Demo\Rabbit\Worker\Handler\ManagerHandler;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\Consumer\Handler\MessageHandler;
use Ipedis\Rabbit\Consumer\Handler\MessageHandlerInterface;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
use Ipedis\Rabbit\Order\Manager as ManagerTrait;


class Manager extends ConnectorAbstract
{
    use ManagerTrait;

    /**
     * @var MessageHandler
     */
    protected $messageHandler;

    /**
     * @var ChannelFactory $channelFactory
     */
    private $channelFactory;

    /**
     * Manager constructor.
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $exchange
     * @param string $type
     * @param ChannelFactory $channelFactory
     */
    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $exchange,
        string $type,
        ChannelFactory $channelFactory
    ) {
        parent::__construct($host, $port, $user, $password, $exchange, $type);

        $this->channelFactory = $channelFactory;
        $this->connect();
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
        $this->messageHandler = (new ManagerHandler())
            ->bind(MessageHandlerInterface::TYPE_PROGRESS, function(ReplyToMessagePayload $messagePayload) {
                print_r("\t ======> In progress binded handler :) - ".json_encode($messagePayload->getData())."\n\n\n");
            })
        ;
        
        $anoQueue = $this->bindCallbackToAnonymousQueue($this->messageHandler);

        /**
         * We publish N Tasks on queue "Worker" who should be consume by this Worker.
         * We give also Anonymous callback Queue to have feedback from worker.
         */
        for ($i = 0; $i < $this->messageHandler->getNumberTask(); $i++) {
            $this->publishTask(OrderMessagePayload::build(
                OrderChannel::fromString('v1.admin.publication.generate'),
                $anoQueue,
                [
                    "hasToFail" => $i % 2 === 0, // Simulate failure on each pair message.
                    "name"      => "task {$i}"
                ]
            ));
        }
        print_r('all message are published on queue'."\n");

        /**
         * Wait all tasks.
         */
        while(count($this->messageHandler->getCompletedTasks()) !== count($this->getDispatchedTasks())) {
            $this->channel->wait();
        }

        printf("%s task are currently traited on queue : %s . Full traitment done :). \n", count($this->messageHandler->getCompletedTasks()), Worker::class);
    }

    /**
     * @return ChannelFactory
     */
    public function getChannelFactory(): ChannelFactory
    {
        return $this->channelFactory;
    }
}
