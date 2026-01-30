<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\MessagePayload\Validator;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;

interface ValidatorInterface
{
    /**
     * Validate message data property
     *
     * @return mixed
     * @throws MessagePayloadInvalidSchemaException
     */
    public function validate(MessagePayloadInterface $messagePayload);
}
