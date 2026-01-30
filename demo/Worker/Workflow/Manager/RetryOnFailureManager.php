<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;

class RetryOnFailureManager extends ManagerAbstract
{
    public function main(): void
    {
        $workflow = new Workflow(
            function (Group $group): void {
                $group->planifyOrder(OrderMessagePayload::build((string)OrderChannel::fromString('v1.admin.publication.failure')), [
                    BindableEventInterface::TASK_ON_RETRY => function (Task $task, string $eventName): void {
                        print_r(sprintf("---- TASK -> RETRY -> %d times \n", $task->getRetryCount()));
                    },
                ]);
            },
            [],
            (new WorkflowConfig(false, true))
        );

        $workflow->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, function () use ($workflow): void {
            print_r(json_encode($workflow->getProgressBag()->getWorkflowProgress(), JSON_PRETTY_PRINT)."\n\n");
        });

        $this->run($workflow);
    }
}
