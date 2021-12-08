<?php
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
    /**
     * @var JsonValidator
     */
    private JsonValidator $jsonValidator;

    private ?ValidationError $error = null;
    /**
     * @var bool
     */
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
        $schemaJson = $validator->loader()->loadObjectSchema(json_decode($schema, false));
        $this->error = $validator->schemaValidation($dataJson, $schemaJson);
        return is_null($this->error);
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isInputValid() &&
            $this->error === null;
    }

    /**
     * @return bool
     */
    public function isInputValid(): bool
    {
        return $this->inputValid;
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
        if (null === $this->error) {
            return '';
        }

        return sprintf(
            "Error: %s\n%s\n",
            $this->error->keyword(),
            json_encode($this->error->args(), JSON_PRETTY_PRINT)
        );
    }
}
