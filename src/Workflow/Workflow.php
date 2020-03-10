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
         * get all scheduled groups
         */
        $plannedGroups = $this->groups;

       while(count($plannedGroups) !== 0) {
           /**
            * Get first/next group by order
            * @var WorkflowGroup $currentGroup
            */
           $currentGroup = array_shift($plannedGroups);

            printf("Workflow Group %s ; Executing new workflow id %s ...\n", $currentGroup->getOrder(), $currentGroup->getGroupId());

            foreach ($currentGroup->getTasks() as $task) {
                sleep(2);
                printf("\n\nExecuting task with payload %s\n", json_encode($task));

                /**
                 * Priority 1 : Task callback
                 */
                $taskCallback = $task['callback'];
                $taskCallback();

                /**
                 * Priority 2 : Group callback if any
                 */
                if ($currentGroup->hasCallback()) {
                    $groupCallback = $currentGroup->getCallback();
                    $groupCallback();
                }

                /**
                 * Priority 3 : Global callbacks if any
                 */
                foreach ($this->callbacks as $callback) {
                    $callback();
                }
            }

            printf("Workflow Group %s completed execution...moving to next workflow if any \n\n\n", $currentGroup->getOrder());
       }
    }

    /**
     * Factory method to create new group
     *
     * @param callable|null $callback
     * @return WorkflowGroup
     */
    private function createNewGroup(?callable $callback): WorkflowGroup
    {
        $currentOrder = count($this->groups);

        return WorkflowGroup::build(++$currentOrder, $callback);
    }

    /**
     * Schedule group using order
     *
     * @param WorkflowGroup $group
     */
    private function scheduleGroup(WorkflowGroup $group)
    {
        $this->groups[$group->getOrder()] = $group;
    }
}
