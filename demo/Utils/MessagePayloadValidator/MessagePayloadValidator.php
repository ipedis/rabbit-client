<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Utils\MessagePayloadValidator;

use Ipedis\Rabbit\Exception\MessagePayload\MessagePayloadInvalidSchemaException;
use Ipedis\Rabbit\MessagePayload\MessagePayloadInterface;
use Ipedis\Rabbit\MessagePayload\Validator\ValidatorInterface;
use Opis\JsonSchema\Validator;

class MessagePayloadValidator implements ValidatorInterface
{
    public const CHANNEL_NAME_SEPARATOR = '.';

    private readonly Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    /**
     * @throws MessagePayloadInvalidSchemaException
     */
    public function validate(MessagePayloadInterface $messagePayload): void
    {
        /**
         * Load schema
         */
        $schemaAbsolutePath = $this->getSchemaPath($messagePayload);
        $schema = $this->validator->loader()->loadObjectSchema(json_decode(file_get_contents($schemaAbsolutePath)));

        /**
         * Transform data to object
         */
        $data = json_decode($messagePayload->getStringifyData());

        $result = $this->validator->validate($data, $schema);

        if (!$result->isValid()) {
            throw new MessagePayloadInvalidSchemaException(sprintf('Invalid schema found for channel {%s}', $messagePayload->getChannel()));
        }
    }

    /**
     * @throws MessagePayloadInvalidSchemaException
     */
    private function getSchemaPath(MessagePayloadInterface $messagePayload): string
    {
        $schemaPath = str_replace(self::CHANNEL_NAME_SEPARATOR, DIRECTORY_SEPARATOR, $messagePayload->getChannel());
        $schemaAbsolutePath = sprintf('%s/../../documents/schema/%s/schema.json', __DIR__, $schemaPath);

        if (!file_exists($schemaAbsolutePath)) {
            throw new MessagePayloadInvalidSchemaException(sprintf('No schema found for channel {%s}', $messagePayload->getChannel()));
        }

        return $schemaAbsolutePath;
    }
}
