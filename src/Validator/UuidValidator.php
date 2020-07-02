<?php


namespace Ipedis\Rabbit\Validator;


use Ipedis\Rabbit\Exception\InvalidUuidException;

class UuidValidator
{
    /**
     * @param string $uuid
     * @return bool
     */
    public function isValid(string $uuid): bool
    {
        return uuid_is_valid($uuid);
    }

    /**
     * @param string $uuid
     * @throws InvalidUuidException
     */
    public function validate(string $uuid)
    {
        if (!$this->isValid($uuid)) {
            throw new InvalidUuidException("{$uuid} is not a valid uuid.");
        }
    }
}