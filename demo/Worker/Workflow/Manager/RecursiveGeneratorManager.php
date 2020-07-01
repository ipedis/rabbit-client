<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Closure;
use Ipedis\Rabbit\DTO\Type\Group\GroupedTaskType;
use Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException;
use Ipedis\Rabbit\Exception\Workflow\InvalidWorkflowArgumentException;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Workflow;

class RecursiveGeneratorManager extends ManagerAbstract
{
    const COUNT_PAGE = 3;

    public function main()
    {
        $generation = (new Workflow($this->craftFirstGroup()))
            ->then($this->craftSecondGroup())
        ;

        /**
         * Callback
         */
        $generation->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, function () use ($generation) {
            printf(
                "Generation PoC: Each table is one tick of generation - %.2f%% done\n----\n\n",
                $generation->getProgressPercentage()
            );

            printf("| name | status | pourcentage of done |\n|---|---|---|\n");
            /** @var GroupedTaskType[] $types */
            $types = $generation->getProgressBag()->getSummary()->getGroupedTasks()['types'];
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
            $group->planifyOrder(
                OrderMessagePayload::build(
                'v1.admin.publication.generate-html',
                    [
                        'publication' => ['sid' => 1024]
                    ]
                )
            );
        };
    }

    /**
     * @return Closure
     * @throws InvalidGroupArgumentException
     * @throws InvalidWorkflowArgumentException
     */
    private function craftSecondGroup(): Closure
    {
        $imageWorkflow = (new Workflow(function (Group $group) {
            $group->planifyOrder(
                OrderMessagePayload::build(
                    'v1.admin.publication.generate-image',
                    [
                        'publication' => ['sid' => 1024]
                    ]
                )
            );
        }))
        ->then(function (Group $group) {
            foreach ([1, self::COUNT_PAGE] as $page) {
                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-image-dbl-zoomable',
                        [
                            'publication' => ['sid' => 1024],
                            'page' => ['sid' => $page],
                        ]
                    )
                );

                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-image-dbl-thumb',
                        [
                            'publication' => ['sid' => 1024],
                            'page' => ['sid' => $page],
                        ]
                    )
                );
            }

            for ($page = 2; $page <= self::COUNT_PAGE; $page += 2) {
                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-image-dbl-zoomable',
                        [
                            'publication' => ['sid' => 1024],
                            'page' => ['sid' => $page],
                        ]
                    )
                );

                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-image-dbl-thumb',
                        [
                            'publication' => ['sid' => 1024],
                            'page' => ['sid' => $page],
                        ]
                    )
                );
            }
        });

        return function (Group $group) use ($imageWorkflow) {
            $group->planifyWorkflow($imageWorkflow);
        };
    }
}
