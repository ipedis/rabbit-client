<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/16/20
 * Time: 1:44 PM
 */
namespace Ipedis\Rabbit\Validator;

class JsonValidator
{
    public function isValid(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
