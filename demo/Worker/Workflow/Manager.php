<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow;


use Closure;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class Manager extends ConnectorAbstract
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
            ->bind(BindableEventInterface::WORKFLOW_ON_START, function () {
                $this->print("WORKFLOW START \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_FINISH, function () {
                $this->print("WORKFLOW FINISH \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_FAILURE, function () {
                $this->print("WORKFLOW FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_SUCCESS, function () {
                $this->print("WORKFLOW SUCCESS \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, function () {
                $this->print("WORKFLOW GROUPS SUCCESS \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE, function () {
                $this->print("WORKFLOW GROUPS FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, function () {
                $this->print("WORKFLOW TASKS FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_TASKS_SUCCESS, function () {
                $this->print("WORKFLOW TASKS SUCCESS \n");
            })
        ;

        $this->run($workflow);
    }

    private function craftFirstStep(): Closure
    {
        return function (Group $group) {
             $group
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    [
                        BindableEventInterface::TASK_ON_START => function() { $this->print("---- TASK 1.1 START \n"); },
                        BindableEventInterface::TASK_ON_PROGRESS => function() { $this->print("---- TASK 1.1 PROGRESS \n"); },
                        BindableEventInterface::TASK_ON_FAILURE => function() { $this->print("---- TASK 1.1 FAILURE \n"); },
                        BindableEventInterface::TASK_ON_SUCCESS => function() { $this->print("---- TASK 1.1 SUCCESS \n"); },
                        BindableEventInterface::TASK_ON_FINISH => function(Task $task) { $this->print("---- TASK 1.1 FINISH \n"); },
                    ]
                )
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    [
                        BindableEventInterface::TASK_ON_START => function() { $this->print("---- TASK 1.2 START \n"); },
                        BindableEventInterface::TASK_ON_PROGRESS => function() { $this->print("---- TASK 1.2 PROGRESS \n"); },
                        BindableEventInterface::TASK_ON_FAILURE => function() { $this->print("---- TASK 1.2 FAILURE \n"); },
                        BindableEventInterface::TASK_ON_SUCCESS => function() { $this->print("---- TASK 1.2 SUCCESS \n"); },
                        BindableEventInterface::TASK_ON_FINISH => function() { $this->print("---- TASK 1.2 FINISH \n"); },
                    ]
                )
                ->bind(BindableEventInterface::GROUP_ON_START, function() {
                    $this->print("-- GROUP 1 START \n");
                })
                 ->bind(BindableEventInterface::GROUP_ON_TASKS_SUCCESS, function() {
                     $this->print("-- GROUP TASKS 1 SUCCESS \n");
                 })
                 ->bind(BindableEventInterface::GROUP_ON_TASKS_FAILURE, function() {
                     $this->print("-- GROUP TASKS 1 FAILURE \n");
                 })
                 ->bind(BindableEventInterface::GROUP_ON_FAILURE, function() {
                     $this->print("-- GROUP 1 FAILURE \n");
                 })
                 ->bind(BindableEventInterface::GROUP_ON_SUCCESS, function() {
                     $this->print("-- GROUP 1 SUCCESS \n");
                 })
                ->bind(BindableEventInterface::GROUP_ON_FINISH, function() {
                    $this->print("-- GROUP 1 FINISH \n");
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
            $task1 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success'))))
                ->bind(BindableEventInterface::TASK_ON_START, function() { $this->print("---- TASK 2.1 START \n"); })
                ->bind(BindableEventInterface::TASK_ON_PROGRESS, function() { $this->print("---- TASK 2.1 PROGRESS \n"); })
                ->bind(BindableEventInterface::TASK_ON_FAILURE, function() { $this->print("---- TASK 2.1 FAILURE \n"); })
                ->bind(BindableEventInterface::TASK_ON_SUCCESS, function() { $this->print("---- TASK 2.1 SUCCESS \n"); })
                ->bind(BindableEventInterface::TASK_ON_FINISH, function() { $this->print("---- TASK 2.1 FINISH \n"); })
            ;
            /**
             * On your convenance you can use array of callbacks or bind method to attach callback to a specific business moment of the runned workflow.
             */
            $craftedGroup = (Group::build(
                [
                    $task1
                ],
                [
                    BindableEventInterface::GROUP_ON_START => function() { $this->print("-- GROUP 2 START \n"); },
                    BindableEventInterface::GROUP_ON_TASKS_SUCCESS => function() { $this->print("-- GROUP TASKS 2 SUCCESS \n"); },
                    BindableEventInterface::GROUP_ON_TASKS_FAILURE => function() { $this->print("-- GROUP TASKS 2 FAILURE \n"); },
                    BindableEventInterface::GROUP_ON_FAILURE => function() { $this->print("-- GROUP 2 FAILURE \n"); },
                    BindableEventInterface::GROUP_ON_SUCCESS => function() { $this->print("-- GROUP 2 SUCCESS \n"); },
                    BindableEventInterface::GROUP_ON_FINISH => function() { $this->print("-- GROUP 2 FINISH \n"); },
                ]
            ));

            /**
             * you can easilly bind, and planify some task base on your own logic.
             */
            if (true) {
                $task2 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.failure'))))
                    ->bind(BindableEventInterface::TASK_ON_START, function() { $this->print("---- TASK 2.2 START \n"); })
                    ->bind(BindableEventInterface::TASK_ON_PROGRESS, function() { $this->print("---- TASK 2.2 PROGRESS \n"); })
                    ->bind(BindableEventInterface::TASK_ON_FAILURE, function() { $this->print("---- TASK 2.2 FAILURE \n"); })
                    ->bind(BindableEventInterface::TASK_ON_SUCCESS, function() { $this->print("---- TASK 2.2 SUCCESS \n"); })
                    ->bind(BindableEventInterface::TASK_ON_FINISH, function() { $this->print("---- TASK 2.2 FINISH \n"); })
                ;

                $craftedGroup->planify($task2);
            }
            /**
             * if you return specific group, it will replace what you have received as params of current Closure.
             */
            return $craftedGroup;
        };
    }

    private function print(string $message)
    {
        print_r($message);
    }
}
