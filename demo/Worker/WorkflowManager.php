<?php

namespace Ipedis\Demo\Rabbit\Worker;


use Closure;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Task;
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

    public function main()
    {
        $workflow = (
            new Workflow($this->craftFirstStep()))
            ->then($this->craftSecondStep())
        ;

        /**
         * We can bind event on workflow layer.
         */
        $workflow
            ->bind(BindableEventInterface::WORKFLOW_START, function () {
                printf("WORKFLOW START \n\n");
            })
            ->bind(BindableEventInterface::WORKFLOW_FINISH, function () {
                printf("WORKFLOW FINISH \n\n");
            })
        ;

        $this->run($workflow);
    }

    private function craftFirstStep(): Closure
    {
        return function (Group $group) {
             $group
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1')),
                    [
                        BindableEventInterface::TASK_START => function() { printf("---- TASK 1.1 START \n\n"); },
                        BindableEventInterface::TASK_FINISH => function(Task $task) { printf("---- TASK 1.1 FINISH \n\n"); },
                        BindableEventInterface::TASK_PROGRESS => function() { printf("---- TASK 1.1 PROGRESS \n\n"); }
                    ]
                )
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step2')),
                    [
                        BindableEventInterface::TASK_START => function() { printf("---- TASK 1.2 START \n\n"); },
                        BindableEventInterface::TASK_FINISH => function() { printf("---- TASK 1.2 FINISH \n\n"); },
                        BindableEventInterface::TASK_PROGRESS => function() { printf("---- TASK 1.2 PROGRESS \n\n"); }
                    ]
                )
                ->bind(BindableEventInterface::GROUP_START, function() {
                    printf("-- GROUP 1 START \n\n");
                })
                ->bind(BindableEventInterface::GROUP_FINISH, function() {
                    printf("-- GROUP 1 FINISH \n\n");
                })
            ;
        };
    }

    /**
     * Demonstrate than we can craft
     *  - your tailor made Group::class.
     *  - your tailor made Task::class.
     *  - combine bulk of task on construct and planify or planifyOrder helper method.
     * @return Closure
     */
    private function craftSecondStep(): Closure
    {
        return function (Group $group) {
            /**
             * You can create your own task from scratch, bind manually your callback.
             * It is useful for conditional and programmatic creation.
             */
            $task1 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1'))))
                ->bind(BindableEventInterface::TASK_START, function() { printf("---- TASK 2.1 START \n\n"); })
                ->bind(BindableEventInterface::TASK_FINISH, function() { printf("---- TASK 2.1 FINISH \n\n"); })
                ->bind(BindableEventInterface::TASK_PROGRESS, function() { printf("---- TASK 2.1 PROGRESS \n\n"); })
            ;
            /**
             * On your convenance you can use array of callbacks or bind method to attach callback to a specific business moment of the runned workflow.
             */
            $craftedGroup = (Group::build(
                [
                    $task1
                ],
                [
                    BindableEventInterface::GROUP_START => function() { printf("-- GROUP 2 START \n\n"); },
                    BindableEventInterface::GROUP_FINISH => function() { printf("-- GROUP 2 FINISH \n\n"); }
                ]
            ));
            /**
             * you can easilly bind, and planify some task base on your own logic.
             */
            if (true) {
                $task2 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.step1'))))
                    ->bind(BindableEventInterface::TASK_START, function() { printf("---- TASK 2.2 START \n\n"); })
                    ->bind(BindableEventInterface::TASK_FINISH, function() { printf("---- TASK 2.2 FINISH \n\n"); })
                    ->bind(BindableEventInterface::TASK_PROGRESS, function() { printf("---- TASK 2.2 PROGRESS \n\n"); })
                ;

                $craftedGroup->planify($task2);
            }
            /**
             * if you return specific group, it will replace what you have received as params of current Closure.
             */
            return $craftedGroup;
        };
    }
}
