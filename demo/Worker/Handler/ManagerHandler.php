<?php


namespace Ipedis\Demo\Rabbit\Worker\Handler;


use Ipedis\Rabbit\Consumer\Handler\MessageHandler;
use PhpAmqpLib\Message\AMQPMessage;

class ManagerHandler extends MessageHandler
{
    /**
     * @var int
     */
    protected $numberTask;
    /**
     * @var int
     */
    protected $count;

    public function __construct()
    {
        $this->numberTask = 10;
        $this->count = 0;
    }

    public function getNumberTask(): int
    {
        return $this->numberTask;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }


    public function onProgress(AMQPMessage $message)
    {
        print_r("\t progress :| - ".$message->getBody());
    }

    public function onSuccess(AMQPMessage $message)
    {
        print_r("\t success :) - ".$message->getBody()."\n\n\n");
        //for sample we just increment counter to determier if all tasks are done.
        $this->count++;
    }

    public function onError(AMQPMessage $message)
    {
        print_r("\t fail :( - ".$message->getBody()."\n\n\n");
        $this->count++;
    }
}
