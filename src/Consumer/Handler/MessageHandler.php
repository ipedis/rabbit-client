<?php

namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * The main method that gets executed
     * when implementing MessageHandlerInterface
     *
     * @param ReplyMessagePayload $message
     */
    public function on(ReplyMessagePayload $message)
    {
        switch (strtolower($message->getStatus())) {
            case self::TYPE_SUCCESS:
                $this->onSuccess($message);
                break;
            case self::TYPE_ERROR:
                $this->onError($message, Error::fromArray($message->getData()['error']));
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($message);
                break;
        }

        /**
         * An error or success eventually leads to completion
         */
        if (
            $message->getStatus() === self::TYPE_SUCCESS ||
            $message->getStatus() === self::TYPE_ERROR
        ) {
            $this->onFinish($message);
        }
    }
}
