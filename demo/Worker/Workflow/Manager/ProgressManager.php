<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;


use Closure;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Manager;
use Ipedis\Rabbit\Workflow\ProgressBag\WorkflowProgressBag;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class ProgressManager extends ConnectorAbstract
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
        /**
         * Having group like following
         * - Group 1
         * ---- Task 1.1 - success
         * ---- Task 1.2 - failure
         * - Group 2
         * ---- Task 2.1 - success
         * ---- Task 2.2 - failure
         */
        $workflow = new Workflow($this->craftGroup('v1.admin.publication.success'));
        $workflow->then($this->craftGroup('v1.admin.publication.waiter'));

        $workflow->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, $this->json($workflow));
        $this->run($workflow);
    }

    public function craftGroup(string $channel, $numberOfTasks = 2, bool $shouldFailed = true): Closure
    {
        return function (Group $group) use ($channel, $numberOfTasks, $shouldFailed) {
            for($i = 0; $i < $numberOfTasks; $i++) {
                $group->planifyOrder(
                    OrderMessagePayload::build(
                        OrderChannel::fromString($channel),
                        ['failure' => $shouldFailed && ($i % 2 === 0)]
                    )
                );
            }
        };
    }

    /**
     * echo completed task : $workflow->getProgressPercentage() . '%'
     * get progress on specific channel $workflow->getProgressBag()->getTasks()->getProgressOnChannel('v1.admin.publication.waiter')
     * get finished tasks $workflow->getProgressBag()->getTasks()->getFinishedTasks()
     * @param Workflow $workflow
     * @return Closure
     */
    public function json(Workflow $workflow): Closure {
        return function () use ($workflow) {
            echo "\n\n\n\n";
            echo json_encode($workflow->getProgressBag()->getSummary()->getGroupedTasks(), JSON_PRETTY_PRINT);
            echo "\n\n\n\n";
        };
    }

    public function print(WorkflowProgressBag $progressBag)
    {
        $done = $progressBag->countTotalCompletedOrders();
        $total = $progressBag->countTotalOrders();

        $perc = floor(($done / $total) * 100);
        $left = 100 - $perc;
        $write = sprintf("\033[0G\033[2K[%'={$perc}s>%-{$left}s] - $perc%% - $done/$total", "", "");
        fwrite(STDERR, $write);
    }
}
