<?php
/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/16/20
 * Time: 1:44 PM
 */

namespace Ipedis\Rabbit\Validator;

use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;

class PayloadValidator
{
    /**
     * @var JsonValidator
     */
    private JsonValidator $jsonValidator;

    /**
     * @var ValidationResult|null
     */
    private ?ValidationResult $validationResult = null;

    /**
     * @var bool
     */
    private bool $inputValid = false;

    public function __construct()
    {
        $this->jsonValidator = new JsonValidator();
    }

    public function validate(string $payload, string $schema)
    {
        // no need to proceed if json in invalid
        if (!$this->jsonValidator->isValid($payload) ||
            !$this->jsonValidator->isValid($schema)
        ) {
            return;
        }

        $this->inputValid = true;
        $dataJson = json_decode($payload);
        $schemaJson = Schema::fromJsonString($schema);
        $validator = new Validator();

        /** @var ValidationResult $result */
        $this->validationResult = $validator->schemaValidation($dataJson, $schemaJson);
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isInputValid() &&
            $this->validationResult &&
            $this->validationResult->isValid();
    }

    /**
     * @return bool
     */
    public function isInputValid(): bool
    {
        return $this->inputValid;
    }

    /**
     * @return null|ValidationResult
     */
    public function getValidationResult(): ?ValidationResult
    {
        return $this->validationResult;
    }

    /**
     * @return JsonValidator
     */
    public function getJsonValidator(): JsonValidator
    {
        return $this->jsonValidator;
    }

    /**
     * @return string
     */
    public function getErrorAsString(): string
    {
        if (null === $this->validationResult) {
            return '';
        }

        /** @var ValidationError $error */
        $error = $this->validationResult->getFirstError();

        return sprintf(
            "Error: %s\n%s\n",
            $error->keyword(),
            json_encode($error->keywordArgs(), JSON_PRETTY_PRINT)
        );
    }
}
