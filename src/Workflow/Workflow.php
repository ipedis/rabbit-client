<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Exception\Workflow\InvalidWorkflowArgumentException;

class Workflow
{
    /**
     * @var array $groups
     */
    protected $groups;

    /**
     * @var array $callbacks
     */
    protected $callbacks;

    /**
     * Workflow constructor.
     *
     * @param Group|callable $firstStep
     * @param callable|null $groupCallback
     * @throws InvalidWorkflowArgumentException
     */
    public function __construct($firstStep, ?callable $groupCallback = null)
    {
        /**
         * Initialise collections
         */
        $this->groups = [];
        $this->callbacks = [];

        /**
         * $fistStep should be either a Group or a callable :
         * - Group : add group to collection
         * - Callable : create and provide new group to callable
         */
        $this->scheduleGroup($firstStep, $groupCallback);
    }

    /**
     * Callbacks to run for the complete workflow
     *
     * @param callable $callback
     * @return Workflow
     */
    public function bind(callable $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Schedule next group of orders
     *
     * @param $nextStep
     * @param callable|null $groupCallback
     * @return Workflow
     * @throws InvalidWorkflowArgumentException
     */
    public function then($nextStep, ?callable $groupCallback = null): self
    {
        $this->scheduleGroup($nextStep, $groupCallback);

        return $this;
    }

    public function run()
    {
        /**
         * Each groups will be executed sequencially, we iterate on each group.
         * call relevant callback if needed.
         */
        /** @var Group $group */
        foreach ($this->groups as $group) {
            // TODO : dispatch event group start.

            foreach ($group->getTasks() as $task) {
                //TODO : dispatch order.
            }
            // TODO : wait for answser.
            //TODO : dispatch global finish.
        }
    }

    /**
     * Attach group of task to workflow
     *
     * - Group provided, add group to collection
     * - Callable provided, create group and pass it to callable
     *   (which can add tasks to the group)
     *
     * @param $step
     * @param callable|null $groupCallback
     * @throws InvalidWorkflowArgumentException
     */
    private function scheduleGroup($step, ?callable $groupCallback = null)
    {
        if (
            !$step instanceof Group &&
            !is_callable($step)
        ) {
            throw new InvalidWorkflowArgumentException('Argument should be either instance of Group or a callable');
        }

        if ($step instanceof Group) {
            /**
             * Add group to collection
             */
            $this->groups[] = $step;
        } else {
            /**
             * Create and provide callable with new group
             */
            $workflowGroup = Group::build($groupCallback);

            /**
             * Callable to add tasks in workflow group
             */
            $step($workflowGroup);

            /**
             * Plan/Schedule the workflow group
             */
            /**
             * Add group to collection
             */
            $this->groups[] = $workflowGroup;
        }
    }
}
