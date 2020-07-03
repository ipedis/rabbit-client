<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Closure;
use Ipedis\Rabbit\DTO\Type\Group\GroupedTaskType;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;

use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Group;
use Ipedis\Rabbit\Workflow\Manager;
use Ipedis\Rabbit\Workflow\ProgressBag\Model\GroupedTasksProgress;
use Ipedis\Rabbit\Workflow\Workflow;

class GeneratorManager extends ManagerAbstract
{
    const COUNT_PAGE = 10;

    public function main()
    {
        $generation = (new Workflow($this->craftFirstGroup()))
            ->then($this->craftSecondGroup())
            ->then($this->crafThirdGroup())
        ;

        $generation->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, function () use ($generation) {
            printf(
                "Generation PoC: Each table is one tick of generation - %.2f%% done\n----\n\n",
                $generation->getProgressPercentage()
            );

            printf("| name | status | pourcentage of done |\n|---|---|---|\n");
            /** @var GroupedTaskType[] $types */
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

        $generation->bind(BindableEventInterface::WORKFLOW_ON_FINISH, function () use ($generation) {
            print_r(sprintf("Summary : %s", json_encode($generation->getProgressBag()->getWorkflowProgress(), JSON_PRETTY_PRINT)));
        })->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_FINISH, function () use ($generation) {
            print_r(sprintf("Summary : %s", json_encode($generation->getProgressBag()->getWorkflowProgress()->getGroupProgressSummary(), JSON_PRETTY_PRINT)));
        })
        ;

        $this->run($generation);
    }

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }

    /**
     * In concurrency we can have html and image
     * @return Closure
     */
    private function craftFirstGroup(): Closure {
        return function (Group $group) {
            $group->planifyOrder(
                OrderMessagePayload::build(
                    'v1.admin.publication.generate-html',
                    [
                        'publication' => ['sid' => 1024]
                    ]
                )
            );

            $group->planifyOrder(
                OrderMessagePayload::build(
                    'v1.admin.publication.generate-image',
                    [
                        'publication' => ['sid' => 1024]
                    ]
                )
            );
        };
    }

    private function craftSecondGroup(): Closure {
        return function (Group $group) {
            for ($page = 1; $page <= self::COUNT_PAGE; $page++) {
                $group->planifyOrder(
                    OrderMessagePayload::build(
                        'v1.admin.publication.generate-image-page',
                        [
                            'publication' => ['sid' => 1024],
                            'page' => ['sid' => $page],
                        ]
                    )
                );
            }
        };
    }

    private function crafThirdGroup(): Closure {
        return function (Group $group) {

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
        };
    }
}
