<?php


namespace Ipedis\Rabbit\Event;


use Closure;
use Ipedis\Rabbit\Connector;
use PhpAmqpLib\Message\AMQPMessage;

trait EventListener
{
    use Connector;
    /**
     * @var string
     */
    protected $worker_id;

    /**
     * @description execution cycle time.
     */
    public function execute()
    {
        $this->worker_id = uniqid("worker_id_");
        $this->connect();
        $this->queueDeclare();
        $this->queueConsume();
        while(count($this->channel->callbacks)) {
            $this->channel->wait();
        }
        $this->disconnect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * override from worker.
     */
    protected function queueDeclare()
    {
        $this->channel->exchange_declare(
            $this->getExchangeName(),
            $this->getExchangeType()
        );
        list($queue, ,) = $this->channel->queue_declare('', false, false, true, false);
        $this->channel->queue_bind($queue,$this->getExchangeName(), $this->getBindingKey());
    }
    /**
     * override from worker.
     */
    protected function queueConsume()
    {
        $this->channel->basic_consume(
            '', //queue
            '', //consumer tag
            false, //no local
            true, //no ack
            false, //exclusive
            false, //no wait
            [$this,"main"] //$this->mainWork(AMQPMessage $req) callback
        );
    }

    /**
     * @param AMQPMessage $req
     */
    public function main(AMQPMessage $req)
    {
        try {
            $this->makeMessageHandler()($req);
        } catch (\Exception $exception) {}
    }

    abstract protected function makeMessageHandler(): Closure;
    abstract protected function getBindingKey(): string;
}
