<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Workflow\Manager;

use Closure;
use Ipedis\Rabbit\Channel\OrderChannel;
use Ipedis\Rabbit\MessagePayload\OrderMessagePayload;
use Ipedis\Rabbit\Workflow\Config\WorkflowConfig;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;
use Ipedis\Rabbit\Workflow\Task;
use Ipedis\Rabbit\Workflow\Workflow;
use Ipedis\Rabbit\Workflow\Group;

class NoFailureManager extends ManagerAbstract
{
    public const ACTIVATE_FULL_LOG = true;

    /**
     * @throws \Ipedis\Rabbit\Exception\Group\InvalidGroupArgumentException
     * @throws \Ipedis\Rabbit\Exception\Workflow\InvalidWorkflowArgumentException
     */
    public function main(): void
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

        print_r(sprintf("Summary : %s", json_encode($workflow->getProgressBag()->getWorkflowProgress())));
    }

    public function getQueuePrefix(): string
    {
        return 'demo.workflow';
    }

    private function craftFirstStep(): Closure
    {
        return function (Group $group): void {
            $group
                ->planifyOrder(
                    OrderMessagePayload::build((string)OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('1.1')
                )
                ->planifyOrder(
                    OrderMessagePayload::build((string)OrderChannel::fromString('v1.admin.publication.failure')),
                    $this->getTaskEvents('1.2')
                );
        };
    }

    /**
     * Demonstrate than we can craft
     *  - your tailor made Group::class.
     *  - your tailor made Task::class.
     *  - combine bulk of task on construct and planify or planifyOrder helper method.
     */
    private function craftSecondStep(): Closure
    {
        return function (Group $group): void {
            $group
                ->planifyOrder(
                    OrderMessagePayload::build((string)OrderChannel::fromString('v1.admin.publication.success')),
                    $this->getTaskEvents('2.1')
                )
                ->planifyOrder(
                    OrderMessagePayload::build((string)OrderChannel::fromString('v1.admin.publication.success')),
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

    protected function bindWorkflowEvents(Workflow $workflow): Workflow
    {
        $workflow
            /**
             * Once on workflow. you will receive as parameter the event name.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_FAILURE, function (string $eventName): void {
                $this->print("WORKFLOW FAILURE \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_SUCCESS, function (string $eventName): void {
                $this->print("WORKFLOW SUCCESS \n");
            })
        ;

        $workflow
            /**
             * On each groups failure or success. you will receive as parameter the actual group and event name.
             */
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_SUCCESS, function (Group $group, string $eventName): void {
                $this->print("WORKFLOW GROUPS SUCCESS \n");
            })
            ->bind(BindableEventInterface::WORKFLOW_ON_GROUPS_FAILURE, function (Group $group, string $eventName): void {
                $this->print("WORKFLOW GROUPS FAILURE \n");
            });

        return $workflow;
    }

    private function getGroupEvents(string $id): array
    {
        return array_merge([
            /**
             * once, on group layer, you will receive as parameter the actual group and event name.
             */
            BindableEventInterface::GROUP_ON_FAILURE => function (Group $group, string $eventName) use ($id): void {
                $this->print("-- GROUP {$id} FAILURE \n");
            },
            BindableEventInterface::GROUP_ON_SUCCESS => function (Group $group, string $eventName) use ($id): void {
                $this->print("-- GROUP {$id} SUCCESS \n");
            },
        ], self::ACTIVATE_FULL_LOG ? [
            /**
             * On each tasks failure or success, you will receive as parameter the actual task and the eventName.
             */
            BindableEventInterface::GROUP_ON_TASKS_SUCCESS => function (Task $task, string $eventName) use ($id): void {
                $this->print("-- GROUP TASKS {$id} SUCCESS \n");
            },
            BindableEventInterface::GROUP_ON_TASKS_FAILURE => function (Task $task, string $eventName) use ($id): void {
                $this->print("-- GROUP TASKS {$id} FAILURE \n");
            },
        ] : []);
    }

    private function getTaskEvents(string $id): array
    {
        /**
         * On task layer, you will receive as parameter the actual task and the eventName.
         */
        return self::ACTIVATE_FULL_LOG ? [
            BindableEventInterface::TASK_ON_FAILURE => function (Task $task, string $eventName) use ($id): void {
                $this->print("---- TASK {$id} FAILURE \n");
            },
            BindableEventInterface::TASK_ON_SUCCESS => function (Task $task, string $eventName) use ($id): void {
                $this->print("---- TASK {$id} SUCCESS \n");
            },
        ] : [];
    }

    private function print(string $message): void
    {
        print_r($message);
    }
}
