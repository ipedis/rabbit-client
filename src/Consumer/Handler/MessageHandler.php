<?php


namespace Ipedis\Rabbit\Consumer\Handler;

use PhpAmqpLib\Message\AMQPMessage;

abstract class MessageHandler implements MessageHandlerInterface
{
    protected $tasksCompleted = [];

    /**
     * @param AMQPMessage $req
     */
    public function on(AMQPMessage $req)
    {
        $data = json_decode($req->getBody(), true);

        switch (strtolower($data['data'][self::STATUS_KEY]))
        {
            case self::TYPE_SUCCESS:
                $this->tasksCompleted[] = $data['header']['correlation_id'];

                $this->onSuccess($req);
                $this->onFinish($req);
            break;
            case self::TYPE_ERROR:
                $this->tasksCompleted[] = $data['header']['correlation_id'];

                $this->onError($req);
                $this->onFinish($req);
            break;
            case self::TYPE_PROGRESS:
                $this->onProgress($req);
            break;
        }
    }

    public function getCompletedTasks(): array
    {
        return $this->tasksCompleted;
    }
}
