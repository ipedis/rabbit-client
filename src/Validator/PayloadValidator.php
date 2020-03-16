<?php
/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/16/20
 * Time: 1:44 PM
 */

namespace Ipedis\Rabbit\Validator;

use Opis\JsonSchema\{
    Validator, ValidationResult, ValidationError, Schema
};

class PayloadValidator
{
    /**
     * @var JsonValidator 
     */
    private $jsonValidator;

    /**
     * @var ValidationResult|null
     */
    private $validationResult = null;

    /**
     * @var bool
     */
    private $inputValid = false;

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
    public function isInputValid(): bool
    {
        return $this->inputValid;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return $this->isInputValid() &&
            $this->validationResult &&
            $this->validationResult->isValid()
            ;
    }

    /**
     * @return null|ValidationResult
     */
    public function getValidationResult(): ValidationResult
    {
        return $this->validationResult;
    }

    public function getJsonValidator(): JsonValidator
    {
        return $this->jsonValidator;
    }
    public function getErrorAsString()
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