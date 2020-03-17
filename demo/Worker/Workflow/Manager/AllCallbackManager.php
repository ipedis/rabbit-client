<?php

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;


use Closure;
use Ipedis\Demo\Rabbit\Utils\ConnectorAbstract;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Manager;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class AllCallbackManager extends ConnectorAbstract
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
        $workflow = (
            new Workflow($this->craftFirstStep()))
            ->then($this->craftSecondStep())
        ;

        /**
         * We can bind event on workflow layer.
         */
        $workflow = $this->bindWorkflowEvents($workflow);

        $this->run($workflow);
    }

    private function craftFirstStep(): Closure
    {
        return function (Group $group) {
             $group
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('1.1')
                )
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('1.2')
                );
            /**
             * You can bind on group layer your callback.
             */
            foreach ($this->getGroupEvents('1') as $event => $closure) {
                $group->bind($event, $closure);
             }
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
            $task1 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success'))));
            foreach ($this->getTaskEvents('2.1') as $eventType => $closure) {
                $task1->bind($eventType, $closure);
            }

            /**
             * On your convenance you can use array of callbacks or bind method to attach callback to a specific business moment of the runned workflow.
             */
            $craftedGroup = (Group::build(
                [
                    $task1
                ],
                $this->getGroupEvents('2')
            ));

            /**
             * you can easilly bind, and planify some task base on your own logic.
             */
            if (true) {
                $task2 = (Task::build(OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success'))));
                foreach ($this->getTaskEvents('2.2') as $eventType => $closure) {
                    $task2->bind($eventType, $closure);
                }
                $craftedGroup->planify($task2);
            }
            /**
             * if you return specific group, it will replace what you have received as params of current Closure.
             */
            return $craftedGroup;
        };
    }

    /**
     * @param Workflow $workflow
     * @return Workflow
     */
    protected function bindWorkflowEvents(Workflow $workflow): Workflow
    {
        $workflow = $workflow
            /**
             * Once on workflow. you will receive as parameter the event name.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_START, function (string $eventName) {
                $this->print("WORKFLOW START \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_FINISH, function (string $eventName) {
                $this->print("WORKFLOW FINISH \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_FAILURE, function (string $eventName) {
                $this->print("WORKFLOW FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_SUCCESS, function (string $eventName) {
                $this->print("WORKFLOW SUCCESS \n");
            })
            /**
             * On each groups failure or success. you will receive as parameter the actual group and event name.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, function (Group $group, string $eventName) {
                $this->print("WORKFLOW GROUPS SUCCESS \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE, function (Group $group, string $eventName) {
                $this->print("WORKFLOW GROUPS FAILURE \n");
            })
            /**
             * On each tasks failure or success. you will receive as parameter the actual task and the eventName.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FAILURE, function (Task $task, string $eventName) {
                $this->print("WORKFLOW TASKS FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_TASKS_SUCCESS, function (Task $task, string $eventName) {
                $this->print("WORKFLOW TASKS SUCCESS \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_TASKS_FINISH, function (Task $task, string $eventName) {
                $this->print("WORKFLOW TASKS FINISH \n");
            });
        return $workflow;
    }

    /**
     * @param string $id
     * @return array
     */
    private function getGroupEvents(string $id): array
    {
        return [
            /**
             * once, on group layer, you will receive as parameter the actual group and event name.
             */
            BindableEventInterface::GROUP_ON_START => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id START \n");},
            BindableEventInterface::GROUP_ON_FAILURE => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id FAILURE \n");},
            BindableEventInterface::GROUP_ON_SUCCESS => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id SUCCESS \n");},
            BindableEventInterface::GROUP_ON_FINISH => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id FINISH \n");},
            /**
             * On each tasks failure or success, you will receive as parameter the actual task and the eventName.
             */
            BindableEventInterface::GROUP_ON_TASKS_SUCCESS => function(Task $task, string $eventName) use ($id) {$this->print("-- GROUP TASKS $id SUCCESS \n");},
            BindableEventInterface::GROUP_ON_TASKS_FAILURE => function(Task $task, string $eventName) use ($id) {$this->print("-- GROUP TASKS $id FAILURE \n");},
        ];
    }

    /**
     * @param string $id
     * @return array
     */
    private function getTaskEvents(string $id): array
    {
        /**
         * On task layer, you will receive as parameter the actual task and the eventName.
         */
        return [
            BindableEventInterface::TASK_ON_START => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id START \n"); },
            BindableEventInterface::TASK_ON_PROGRESS => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id PROGRESS \n"); },
            BindableEventInterface::TASK_ON_FAILURE => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id FAILURE \n"); },
            BindableEventInterface::TASK_ON_SUCCESS => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id SUCCESS \n"); },
            BindableEventInterface::TASK_ON_FINISH => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id FINISH \n"); },
        ];
    }

    /**
     * @param string $message
     */
    private function print(string $message)
    {
        print_r($message);
    }
}
