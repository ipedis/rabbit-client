<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Order\Manager as ManagerTrait;
use PhpAmqpLib\Message\AMQPMessage;

class Manager extends ConnectorAbstract
{
    use ManagerTrait;
    /**
     * Util Flag to know if is finish or nop.
     */
    protected $numberTask;
    protected $count;

    /**
     * Manager constructor.
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $exchange
     * @param string $type
     */
    public function __construct(string $host, int $port, string $user, string $password, string $exchange, string $type)
    {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->numberTask = 10;
        $this->count = 0;
        $this->connect();
    }


    public function __destruct()
    {
        $this->disconnect();
    }
    public function main()
    {
        $anoQueue = $this->bindCallbackToAnonymousQueue([$this,"callback"]);

        /**
         * We publish N Tasks on queue "Worker" who should be consume by this Worker.
         * We give also Anonymous callback Queue to have feedback from worker.
         */
        for ($i = 0; $i < $this->getNumberTask(); $i++){
            $this->publishTask('v1.admin.publication.generate',
                [
                    "hasToFail" => $i % 2 === 0, // Simulate failure on each pair message.
                    "name"      => "task {$i}"
                ],
                $anoQueue,
                "task".$i
            );
        }
        print_r('all message are published on queue'."\n");
        /**
         * Wait all tasks.
         */
        while ($this->count < $this->getNumberTask()){
            $this->channel->wait();
        }

        printf("%s task are currently traited on queue : %s . Full traitment done :). \n",$this->count, Worker::class);
    }


    public function getNumberTask() {
        return $this->numberTask;
    }

    /**
     * @description callback binded by anonymous queue created and given to worker for feedback message.
     * @param AMQPMessage $message
     */
    public function callback(AMQPMessage $message) {
        $params = json_decode($message->getBody(),true);
        print_r("\n Received message for task with id :  ".$message->get('correlation_id'));
        switch ($params['status']) {
            case "PROGRESS":
                $this->onProgress($message);
                break;
            case "SUCCESS":
                $this->onSuccess($message);
                break;
            case "ERROR":
                $this->onError($message);
                break;
        }

    }

    private function onProgress(AMQPMessage $message)
    {
        print_r("\t progress :| - ".$message->getBody());
    }

    private function onSuccess(AMQPMessage $message)
    {
        print_r("\t success :) - ".$message->getBody()."\n\n\n");
        //for sample we just increment counter to determier if all tasks are done.
        $this->count++;
    }

    private function onError(AMQPMessage $message)
    {
        print_r("\t fail :( - ".$message->getBody()."\n\n\n");
        $this->count++;
    }
}
