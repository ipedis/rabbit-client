<?php

namespace Ipedis\Rabbit\Workflow;


use Ipedis\Rabbit\Exception\Workflow\InvalidWorkflowArgumentException;
use Ipedis\Rabbit\MessagePayload\ReplyMessagePayload;
use Ipedis\Rabbit\Workflow\Event\Bindable;
use Ipedis\Rabbit\Workflow\Event\BindableEventInterface;

class Workflow extends Bindable implements \Iterator
{
    /**
     * @var Group[] $groups
     */
    protected $groups;

    /**
     * @var int use for iterator
     */
    protected $currentRunnedGroup;

    /**
     * Workflow constructor.
     *
     * @param Group|callable $firstStep
     * @param array $groupCallbacks
     * @throws InvalidWorkflowArgumentException
     */
    public function __construct($firstStep, array $groupCallbacks = [])
    {
        /**
         * Initialise collections
         */
        $this->groups = [];

        /**
         * initialize iterable variable.
         */
        $this->currentRunnedGroup = 0;

        /**
         * $fistStep should be either a Group or a callable :
         * - Group : add group to collection
         * - Callable : create and provide new group to callable
         */
        $this->schedule($firstStep, $groupCallbacks);
    }

    /**
     * Schedule next group of orders
     *
     * @param $nextStep
     * @param array $callbacks
     * @return Workflow
     * @throws InvalidWorkflowArgumentException
     */
    public function then($nextStep,  array $callbacks = []): self
    {
        $this->schedule($nextStep, $callbacks);

        return $this;
    }

    public function taskReply(ReplyMessagePayload $message): Group
    {
        foreach ($this->groups as $group) {
            if($group->has($message->getOrderId())) {
                $currentGroup = $group->update($message);
                break;
            }
        }

        return $currentGroup;
    }

    /**
     * Attach group of task to workflow
     *
     * - Group provided, add group to collection
     * - Callable provided, create group and pass it to callable
     *   (which can add tasks to the group)
     *
     * @param $step
     * @param array $callbacks
     * @throws InvalidWorkflowArgumentException
     */
    private function schedule($step, array $callbacks = [])
    {
        $this->assertGroup($step);

        if ($step instanceof Group) {
            /**
             * In case we provide groupCallback, we must bind it.
             */
            foreach ($callbacks as $eventType => $callback) {
                $step->bind($eventType, $callback);
            }
            /**
             * Add group to collection
             */
            $this->groups[] = $step;
        } else {
            /**
             * Create and provide callable with new group
             */
            $workflowGroup = Group::build($callbacks);

            /**
             * Callable to add tasks in workflow group
             */
            $returnedGroup = $step($workflowGroup);

            /**
             * Plan/Schedule the workflow group
             * Add group to collection, if Group are returned by callback, then use it in place of the original one.
             */
            $this->groups[] = ($returnedGroup instanceof Group) ? $returnedGroup : $workflowGroup;
        }
    }

    private function assertGroup($step)
    {
        if (
            !$step instanceof Group &&
            !is_callable($step)
        ) {
            throw new InvalidWorkflowArgumentException(sprintf('Argument should be either instance of "%s" or a callable', Group::class));
        }
    }

    protected function getAllowedBindableTypes(): array
    {
        return BindableEventInterface::WORKFLOW_ALLOW_TYPES;
    }

    /**
     * Section iterator interface, nothing intersting here
     */
    public function rewind()
    {
        $this->currentRunnedGroup = 0;
    }

    public function current()
    {
        return $this->groups[$this->currentRunnedGroup];
    }

    public function key()
    {
        return $this->currentRunnedGroup;
    }

    public function next()
    {
        ++$this->currentRunnedGroup;
    }

    public function valid()
    {
        return isset($this->groups[$this->currentRunnedGroup]);
    }
}
