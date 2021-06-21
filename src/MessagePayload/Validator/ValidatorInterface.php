<?php

namespace Ipedis\Rabbit\MessagePayload\Validator;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;

interface ValidatorInterface
{
    /**
     * Validate message data property
     *
     * @param MessagePayloadInterface $messagePayload
     * @return mixed
     * @throws MessagePayloadInvalidSchemaException
     */
    public function validate(MessagePayloadInterface $messagePayload);
}
