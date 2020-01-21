<?php


namespace Ipedis\Rabbit\Consumer\Handler;

use PhpAmqpLib\Message\AMQPMessage;

abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * @param AMQPMessage $req
     */
    public function on(AMQPMessage $req)
    {
        $data = json_decode($req->getBody(), true);

        switch ($data[self::STATUS_KEY])
        {
            case self::TYPE_SUCCESS:
                $this->onSuccess($req);
            break;
            case self::TYPE_ERROR:
                $this->onError($req);
            break;
            case self::TYPE_PROGRESS:
                $this->onProgress($req);
            break;
        }
    }
}
