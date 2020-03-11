<?php


namespace Ipedis\Demo\Rabbit\Worker;


use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class WorkflowManager extends ConnectorAbstract
{
    use \Ipedis\Rabbit\Order\Manager;

    public function main() {

        $build = new Workflow(
            function (Group $group) {
                $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1')));
                $group->planifyOrder(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1')));
            }
        );

        $build->run();
    }
}
