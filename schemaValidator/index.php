<?php

use Opis\JsonSchema\ValidationError;
use Ipedis\Rabbit\Validator\PayloadValidator;

require '../vendor/autoload.php';

$schema = (!empty($_POST['schema'])) ? $_POST['schema'] : '';
$data = (!empty($_POST['data'])) ? $_POST['data'] : '';

$hasValidation = !empty($_POST['schema']) && !empty($_POST['data']);

if ($hasValidation) {
    $validator = new PayloadValidator();
    $validator->validate($_POST['data'], $_POST['schema']);
}
?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <title>Schema builder</title>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col">
                <?php if($hasValidation): ?>
                    <div class="alert <?php echo ($validator->isValid()) ? 'alert-success' : 'alert-danger' ?>" role="alert">
                        <?php
                            if ($validator->isValid()) {
                                echo '$data is valid', PHP_EOL;
                            } else {
                                if (!$validator->isInputValid()) {
                                    echo 'Invalid JSON provided. Please check json format for schema and data.', PHP_EOL;
                                } else {
                                    /** @var ValidationError $error */
                                    echo '$data is invalid', PHP_EOL;
                                    echo $validator->getErrorAsString();
                                }
                            }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <form method="POST">
            <div class="row">
                <div class="col">
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Schema section</h2>
                                </div>
                                <div class="card-body">
                                    <textarea name="schema" class="form-control" style="height: 70vh;"><?php echo $schema ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    <h2>Data section</h2>
                                </div>
                                <div class="card-body">
                                    <textarea name="data" class="form-control" style="height: 70vh;"><?php echo $data ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col" style="text-align: right">
                            <button class="btn btn-default" id="clear">
                                clear
                            </button>
                            <button class="btn btn-primary" type="submit">
                                validate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
<script>
    (function() {
        document.querySelector('#clear').addEventListener('click', function() {
            Array.from(document.querySelectorAll('textarea')).forEach(function(e) {
               e.value = '';
            });
        });
    })();
</script>
</html>
