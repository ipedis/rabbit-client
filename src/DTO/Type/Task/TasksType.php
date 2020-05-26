<?php


namespace Ipedis\Rabbit\DTO\Type\Task;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;

class TasksType
{
    private $tasks;

    /** @var TaskType[] $tasks*/
    public function __construct(array $tasks)
    {
        $this->assertParams($tasks);
        $this->tasks = $tasks;
    }

    /**
     * @inheritDoc
     */
    public function getSuccessfullTasks(): array
    {
        $tasks = [];
        foreach ($this->tasks as $task) {
            if ($task->isSuccess()) {
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    ChannelAbstract::getTypeFromString($task->getOrderMessage()->getChannel()),
                    StatusType::buildSuccess(),
                    TimerType::build($task->getExecutionTime(), $task->getStartTime(), $task->getFinishedTime())
                );
            }
        }

        return $tasks;
    }

    /**
     * @inheritDoc
     */
    public function getFailedTasks(): array
    {
        $tasks = [];

        foreach ($tasks as $task) {
            if ($task->isOnFailure()) {
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    ChannelAbstract::getTypeFromString($task->getOrderMessage()->getChannel()),
                    StatusType::buildFailed(),
                    TimerType::build($task->getExecutionTime(), $task->getStartTime(), $task->getFinishedTime())
                );
            }
        }

        return $tasks;
    }

    /**
     * @inheritDoc
     */
    public function getRunningTasks(): array
    {
        $tasks = [];

        foreach ($tasks as $task) {
            if ($task->isInProgress()) {
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    ChannelAbstract::getTypeFromString($task->getOrderMessage()->getChannel()),
                    StatusType::buildRunning(),
                    TimerType::build($task->getExecutionTime(), $task->getStartTime(), $task->getFinishedTime())
                );
            }
        }

        return $tasks;
    }

    /**
     * @inheritDoc
     */
    public function getPendingTasks(): array
    {
        $tasks = [];
        foreach ($this->tasks as $task) {
            if ($task->isPlanified()) {
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    ChannelAbstract::getTypeFromString($task->getOrderMessage()->getChannel()),
                    StatusType::buildPending(),
                    TimerType::build($task->getExecutionTime(), $task->getStartTime(), $task->getFinishedTime())
                );
            }
        }

        return $tasks;
    }

    /**
     * @inheritDoc
     */
    public function getTaskSummaryOnChannel(string $channel): array
    {
        $tasks = [];

        foreach ($this->tasks as $task) {
            if ($task->getType() === ChannelAbstract::getTypeFromString($channel)) { //task is for the specified channel
                $tasks[] = TaskType::build(
                    $task->getOrderMessage()->getOrderId(),
                    $task->getType(),
                    $task->getStatusType(),
                    $task->getTimer()
                );
            }
        }

        return [
            'tasks' => $tasks,
            'percentage' => $this->getProgressOnChannel($channel)
        ];
    }

    /**
     * @inheritDoc
     */
    public function getProgressOnChannel(string $channel): ProgressType
    {
        $failed = $completed = $success = 0;
        foreach ($this->tasks as $task) {
            if ($task->getType() === ChannelAbstract::getTypeFromString($channel)) { //task is for the specified channel
                if($task->isCompleted()) {
                    $completed ++;
                }
                if($task->isOnFailure()) {
                    $failed ++;
                } else if ($task->isSuccess()) {
                    $success ++;
                }
            }
        }

        return ProgressType::build(
            ($completed * 100) / $this->countTotalOrders(),
            ($success * 100) / $this->countTotalOrders(),
            ($failed * 100) / $this->countTotalOrders()
        );
    }

    private function countTotalOrders()
    {
        return count($this->tasks);
    }

    private function assertParams(array $tasks)
    {
        foreach ($tasks as $task) {
            if (!$task instanceof TaskType) {
                throw new \InvalidArgumentException('Invalid task type');
            }
        }
    }
}
