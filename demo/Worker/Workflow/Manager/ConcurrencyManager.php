<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Closure;
use Ipedis\Rabbit\Channel\Config\ChannelConfig;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupedTasksProgress;
use Ipedis\Rabbit\Workflow\Workflow;

class ConcurrencyManager extends ManagerAbstract
{
    public function main()
    {
        $workflowConfig = new WorkflowConfig(
            false,
            false,
            3,
            false,
            [
                ChannelConfig::build('v1.admin.publication.generate-html', 2)
            ]
        );

        $generation = (new Workflow($this->craftFirstGroup(), [], $workflowConfig));

        $generation->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, function () use ($generation) {
            printf(
                "Generation PoC: Each table is one tick of generation - %.2f%% done\n----\n\n",
                $generation->getProgressPercentage()
            );

            printf("| name | status | pourcentage of done |\n|---|---|---|\n");
            $types = $generation->getProgressBag()->getWorkflowProgress()->getGroupedTasksSummary()->getGroupedTasksCollection();
            /**
             * @var GroupedTasksProgress $type
             */
            foreach ($types as $name => $type) {
                $pourcentageDone = $type->getSummary()->getCompleted() * 100 / $type->getSummary()->getTotal();
                printf(
                    "| %s | %s | %.2f%% |\n",
                    $name,
                    $type->getStatus(),
                    $pourcentageDone
                );
            }
            printf("\n\n");
        });

        $this->run($generation);
    }

    /**
     * @return Closure
     */
    private function craftFirstGroup(): Closure
    {
        return function(Group $group) {
            for($i=0;$i<10;$i++) {
                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-html',
                        [
                            'publication' => ['sid' => 1024]
                        ]
                    )
                );
            }
        };
    }
}
