<?php

namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class WorkflowManager extends ConnectorAbstract
{
    use \Ipedis\Rabbit\Workflow\Manager;

    public function __construct(string $host, int $port, string $user, string $password, string $exchange, string $type)
    {
        parent::__construct($host, $port, $user, $password, $exchange, $type);
        $this->connect();

        /**
         * Initialise order queue
         */
        $this->resetOrdersQueue();
    }

    public function main() {

        $workflow = (new Workflow(
            function (Group $group) {
                return $group
                    ->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1')))
                    ->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1')))
                ;
            }
        ))->then(function (Group $group) {
            $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step2')));
        });

        $workflow->bind(BindableEventInterface::WORKFLOW_START, function () {
            printf("WORKFLOW START \n\n");
        });

        $workflow->bind(BindableEventInterface::WORKFLOW_FINISH, function () {
            printf("WORKFLOW START \n\n");
        });

        $this->run($workflow);
    }
}
