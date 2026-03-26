<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/16/20
 * Time: 1:44 PM
 */

namespace Ipedis\Rabbit\Validator;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

class PayloadValidator
{
    private readonly JsonValidator $jsonValidator;

    private ?ValidationError $error = null;

    private bool $inputValid = false;

    public function __construct()
    {
        $this->jsonValidator = new JsonValidator();
    }

    public function validate(string $payload, string $schema): bool
    {
        // no need to proceed if json in invalid
        if (!$this->jsonValidator->isValid($payload) ||
            !$this->jsonValidator->isValid($schema)
        ) {
            return false;
        }

        $this->inputValid = true;
        $dataJson = json_decode($payload, false);
        $validator = new Validator();
        /** @var object $schemaDecoded */
        $schemaDecoded = json_decode($schema, false);
        $schemaJson = $validator->loader()->loadObjectSchema($schemaDecoded);
        $this->error = $validator->schemaValidation($dataJson, $schemaJson);
        return is_null($this->error);
    }

    public function isValid(): bool
    {
        return $this->isInputValid() &&
            !$this->error instanceof ValidationError;
    }

    public function isInputValid(): bool
    {
        return $this->inputValid;
    }

    public function getJsonValidator(): JsonValidator
    {
        return $this->jsonValidator;
    }

    public function getErrorAsString(): string
    {
        if (!$this->error instanceof ValidationError) {
            return '';
        }

        return sprintf(
            "Error: %s\n%s\n",
            $this->error->keyword(),
            (string) json_encode($this->error->args(), JSON_PRETTY_PRINT)
        );
    }
}
