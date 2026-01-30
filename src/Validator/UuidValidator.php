<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Validator;

use Ipedis\Rabbit\Exception\InvalidUuidException;

class UuidValidator
{
    /**
     * @throws InvalidUuidException
     */
    public function validate(string $uuid): void
    {
        if (!$this->isValid($uuid)) {
            throw new InvalidUuidException($uuid . ' is not a valid uuid.');
        }
    }

    public function isValid(string $uuid): bool
    {
        return uuid_is_valid($uuid);
    }
}
