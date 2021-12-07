<?php

use Ipedis\Rabbit\Validator\PayloadValidator;

$schema = '{
  "type":"object",
  "properties": {
    "hasToFail": {
      "type":"boolean"
    },
    "name":{
      "type":"string"
    }
  },
  "required": ["hasToFail", "name"],
  "additionalProperties":false
}';


it('should detect valid payload structure string payload with string schema', function (string $schema, string $payload) {
    expect((new PayloadValidator())->validate($payload, $schema))->toBeTrue();
})->with([
    [$schema, '{ "hasToFail": true, "name": "foo" }'],
    [$schema, '{ "hasToFail": false, "name": "" }'],
    [$schema, '{ "name": "foo", "hasToFail": true }'],
]);

it('should detect invalid payload structure string payload with string schema', function (string $schema, string $payload) {
    expect((new PayloadValidator())->validate($payload, $schema))->toBeFalse();
})->with([
    [$schema, '{ "hasToFail": -1, "name": "foo" }'],
    [$schema, '{ "name": "" }'],
    [$schema, '"name": "foo", "hasToFail": true }'],
    [$schema, '{"name": "foo","extra": "ff", "hasToFail": true }'],
]);

it('should detect return readable message when error is detected', function (string $schema, string $payload, string $message) {
    $validatior = new PayloadValidator();
    $validatior->validate($payload, $schema);
    $error = $validatior->getErrorAsString();
    expect($error)->toContain($message);
})->with([
    [$schema, '{ "hasToFail": -1, "name": "foo" }', 'hasToFail'],
    [$schema, '{ "name": "" }', 'hasToFail'],
    [$schema, '{"name": "foo","extra": "ff", "hasToFail": true }', 'extra'],
]);
