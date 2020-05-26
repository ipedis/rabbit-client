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
        $workflow = new Workflow(
            function (Group $group) {
                for($i = 0; $i < 2; $i++) {
                    $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.waiter')));
                }
            }
        );

        $workflow->then(function (Group $group) {
        for($i = 0; $i < 8; $i++) {
                $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.waiter')));
            }
        });

        $workflow->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, function () use ($workflow) {
            /**
             * You can print the actual % like this.
             * print_r($workflow->getProgressBag()->getPercentageProgression()."% \n\n");
             */
            var_dump(json_encode($workflow->getProgressBag()->getSummary()));
        });

        $this->run($workflow);
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
