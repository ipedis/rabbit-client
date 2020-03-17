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

class NoFailureManager extends ConnectorAbstract
{
    use Manager;

    const ACTIVATE_FULL_LOG = true;

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
            $this->craftFirstStep(),
            $this->getGroupEvents('1'),
            /**
             * You can provide configuration to the workflow to control how the workflow run should react to failure.
             */
            new WorkflowConfig(true)
        );

        $workflow = $this
            ->bindWorkflowEvents($workflow)
            ->then($this->craftSecondStep())
        ;

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
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.failure')),
                    $this->getTaskEvents('1.2')
                );
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
            $group
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('2.1')
                )
                ->planifyOrder(
                    OrderMessagePayload::build(OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('2.2')
                );
            /**
             * You can bind on group layer your callback.
             */
            foreach ($this->getGroupEvents('2') as $event => $closure) {
                $group->bind($event, $closure);
            }
        };
    }

    /**
     * @param Workflow $workflow
     * @return Workflow
     */
    protected function bindWorkflowEvents(Workflow $workflow): Workflow
    {
        $workflow
            /**
             * Once on workflow. you will receive as parameter the event name.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_FAILURE, function (string $eventName) {
                $this->print("WORKFLOW FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_SUCCESS, function (string $eventName) {
                $this->print("WORKFLOW SUCCESS \n");
            })
        ;

        if(self::ACTIVATE_FULL_LOG) {
            $workflow
                /**
                 * On each groups failure or success. you will receive as parameter the actual group and event name.
                 */
                ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, function (Group $group, string $eventName) {
                    $this->print("WORKFLOW GROUPS SUCCESS \n");
                })
                ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE, function (Group $group, string $eventName) {
                    $this->print("WORKFLOW GROUPS FAILURE \n");
                });
        }

        return $workflow;
    }

    /**
     * @param string $id
     * @return array
     */
    private function getGroupEvents(string $id): array
    {
        return array_merge([
            /**
             * once, on group layer, you will receive as parameter the actual group and event name.
             */
            BindableEventInterface::GROUP_ON_FAILURE => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id FAILURE \n");},
            BindableEventInterface::GROUP_ON_SUCCESS => function(Group $group, string $eventName) use ($id) {$this->print("-- GROUP $id SUCCESS \n");},
        ], self::ACTIVATE_FULL_LOG ? [
            /**
             * On each tasks failure or success, you will receive as parameter the actual task and the eventName.
             */
            BindableEventInterface::GROUP_ON_TASKS_SUCCESS => function(Task $task, string $eventName) use ($id) {$this->print("-- GROUP TASKS $id SUCCESS \n");},
            BindableEventInterface::GROUP_ON_TASKS_FAILURE => function(Task $task, string $eventName) use ($id) {$this->print("-- GROUP TASKS $id FAILURE \n");},
        ] : []);
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
        return self::ACTIVATE_FULL_LOG ? [
            BindableEventInterface::TASK_ON_FAILURE => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id FAILURE \n"); },
            BindableEventInterface::TASK_ON_SUCCESS => function(Task $task, string $eventName) use ($id) { $this->print("---- TASK $id SUCCESS \n"); },
        ] : [];
    }

    /**
     * @param string $message
     */
    private function print(string $message)
    {
        print_r($message);
    }
}
