<?php

use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\WorkflowGroup;

require __DIR__.'/../vendor/autoload.php';

$workflow = (new Workflow(function(WorkflowGroup $workflowGroup) {
    for ($i = 0; $i < 4; $i++) {
        $workflowGroup->planify(['job' => $i], function() { printf("=> This is from task callback\n"); });
    }}, function() {
        printf("====> This is from group callback\n");
    })
)->then(function(WorkflowGroup $workflowGroup) {
    for ($i = 0; $i < 3; $i++) {
        $workflowGroup->planify(['job' => $i], function() { printf("=> This is from task callback of second workflow group\n"); });
    }
})->bind(function(){
    printf("=======
    > This is from the global callback\n");
})
;


$workflow->run();
