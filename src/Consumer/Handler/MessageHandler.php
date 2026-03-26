<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Consumer\Handler;

use Ipedis\Rabbit\Exception\Helper\Error;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;

abstract class MessageHandler implements MessageHandlerInterface
{
    /**
     * The main method that gets executed
     * when implementing MessageHandlerInterface
     */
    public function on(ReplyMessagePayload $message): void
    {
        switch (strtolower($message->getStatus())) {
            case self::TYPE_SUCCESS:
                $this->onSuccess($message);
                $this->onFinish($message);
                break;
            case self::TYPE_ERROR:
                /** @var array<string, mixed> $errorData */
                $errorData = $message->getData()['error'];
                $this->onError($message, Error::fromArray($errorData));
                $this->onFinish($message);
                break;
            case self::TYPE_PROGRESS:
                $this->onProgress($message);
                break;
            case self::TYPE_STARTING:
                $this->onStarting($message);
                break;
        }
    }
}
