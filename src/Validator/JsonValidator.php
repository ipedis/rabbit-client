<?php
/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/16/20
 * Time: 1:44 PM
 */

namespace Ipedis\Rabbit\Validator;

class JsonValidator
{
    /**
     * @param string $json
     * @return bool
     */
    public function isValid(string $json): bool
    {
        json_decode($json);

        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        }

        return false;
    }
}
