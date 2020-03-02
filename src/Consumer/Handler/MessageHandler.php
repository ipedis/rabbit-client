<?php


namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadFormatException;
use Ipedis\Rabbit\MessagePayload\ReplyToMessagePayload;
use PhpAmqpLib\Message\AMQPMessage;


abstract class MessageHandler implements MessageHandlerInterface
{
    protected $tasksCompleted = [];

    /**
     * @param AMQPMessage $req
     * @throws MessagePayloadFormatException
     */
    public function on(AMQPMessage $req)
    {
        /**
         * Create message payload objectValue from request body
         */
        $messagePayload = ReplyToMessagePayload::fromJson($req->getBody());
        $data = $messagePayload->getData();

        switch (strtolower($data[self::STATUS_KEY]))
        {
            case self::TYPE_SUCCESS:
                $this->tasksCompleted[] = $messagePayload->getTaskId();

                $this->onSuccess($messagePayload);
                $this->onFinish($messagePayload);
                break;
            case self::TYPE_ERROR:
                $this->tasksCompleted[] = $messagePayload->getTaskId();

                $this->onError($messagePayload);
                $this->onFinish($messagePayload);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($messagePayload);
            break;
        }
    }

    public function getCompletedTasks(): array
    {
        return $this->tasksCompleted;
    }
}
