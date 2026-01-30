<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 7/2/20
 * Time: 10:39 AM
 */
namespace Ipedis\Rabbit\Exception\Progress;

use Ipedis\Rabbit\Exception\RabbitClientException;
use Throwable;

class InvalidStatusException extends RabbitClientException
{
    public function __construct(string $message = "Invalid value for status is provided.", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
