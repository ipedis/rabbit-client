<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Manager;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;

class RetryOnFailureManager extends ConnectorAbstract
{
    use Manager;

    public function __construct(string $host, int $port, string $user, string $password, string $exchange, string $type)
    {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->connect();

        /**
         * Initialise order queue
         */
        $this->resetOrdersQueue();
    }

    public function main()
    {
        $workflow = new Workflow(
            function (Group $group) {
                $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.failure')), [
                    BindableEventInterface::TASK_ON_RETRY => function(Task $task, string $eventName) { print_r(sprintf("---- TASK -> RETRY -> %d times \n", $task->getRetryCount())); },
                ]);
            },
            [],
            (new WorkflowConfig(false, true))
        );

        $workflow->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, function () use ($workflow) {
            print_r(json_encode($workflow->getProgressBag()->getSummary())."\n\n");
        });

        $this->run($workflow);
    }
}
