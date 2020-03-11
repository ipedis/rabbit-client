<?php

namespace Ipedis\Rabbit\Workflow;


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

    public function __construct(callable $firstStep, ?callable $groupCallback = null)
    {
        /**
         * Initialise collections
         */
        $this->groups = [];
        $this->callbacks = [];

        /**
         * Create a new workflow group with execution order
         */
        $workflowGroup = $this->createNewGroup($groupCallback);

        /**
         * Callable to add tasks in workflow group
         */
        $firstStep($workflowGroup);

        /**
         * Plan/Schedule the workflow group
         */
        $this->scheduleGroup($workflowGroup);
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
     * @param callable $nextStep
     * @param callable|null $groupCallback
     * @return Workflow
     */
    public function then(callable $nextStep, ?callable $groupCallback = null): self
    {
        /**
         * Create a new workflow group with execution order
         */
        $workflowGroup = $this->createNewGroup($groupCallback);

        /**
         * Callable to add tasks in workflow group
         */
        $nextStep($workflowGroup);

        /**
         * Plan/Schedule the workflow group
         */
        $this->scheduleGroup($workflowGroup);

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
     * Factory method to create new group
     *
     * @param callable|null $callback
     * @return Group
     */
    private function createNewGroup(?callable $callback): Group
    {
        $currentOrder = count($this->groups);

        return Group::build(++$currentOrder, $callback);
    }

    /**
     * Schedule group using order
     *
     * @param Group $group
     */
    private function scheduleGroup(Group $group)
    {
        $this->groups[$group->getOrder()] = $group;
    }
}
