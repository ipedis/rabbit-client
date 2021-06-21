<?php

namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * The main method that gets executed
     * when implementing MessageHandlerInterface
     *
     * @param ReplyMessagePayload $messagePayload
     */
    public function on(ReplyMessagePayload $messagePayload)
    {
        switch (strtolower($messagePayload->getStatus())) {
            case self::TYPE_SUCCESS:
                $this->onSuccess($messagePayload);
                break;
            case self::TYPE_ERROR:
                $this->onError($messagePayload);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($messagePayload);
                break;
        }

        /**
         * An error or success eventually leads to completion
         */
        if (
            $messagePayload->getStatus() === self::TYPE_SUCCESS ||
            $messagePayload->getStatus() === self::TYPE_ERROR
        ) {
            $this->onFinish($messagePayload);
        }
    }
}
