<?php


namespace Ipedis\Demo\Rabbit\Utils\MessagePayloadValidator;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;

class MessagePayloadValidator implements ValidatorInterface
{

    public function validate(MessagePayloadInterface $messagePayload)
    {
        /**
         * Perform validation
         * and throw @MessagePayloadInvalidSchemaException if invalid
         */
        if (!is_array($messagePayload->getData())) {
            throw new MessagePayloadInvalidSchemaException("Message payload is invalid");
        }
    }
}
