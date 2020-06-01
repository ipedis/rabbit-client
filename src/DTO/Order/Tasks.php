<?php


namespace Ipedis\Rabbit\DTO\Order;


use Ipedis\Rabbit\Channel\ChannelAbstract;
use Ipedis\Rabbit\DTO\Type\ProgressType;
use Ipedis\Rabbit\DTO\Type\StatusType;
use Ipedis\Rabbit\DTO\Type\TaskType;
use Ipedis\Rabbit\DTO\Type\TimerType;

class Tasks implements \JsonSerializable
{
    /**
     * @var TaskType[]
     */
    private $tasks;

    /**
     * @var ProgressType
     */
    private $percentage;

    /**
     * @var int
     */
    private $countTotalOrders;

    /**
     * @param ProgressType $percentage
     * @var TaskType[] $tasks
     */
    public function __construct(array $tasks, ProgressType $percentage)
    {
        $this->assertParams($tasks);
        $this->percentage = $percentage;
        $this->tasks = $tasks;
        $this->countTotalOrders = count($tasks);
    }

    /**
     * Get how many tasks was successfully executed
     */
    public function getSuccessfullTasks(): array
    {
        return [
            'tasks' => array_values(
                array_filter($this->tasks, function (TaskType $task) {
                    return $task->getStatus()->isSuccess();
                })
            ),
            'percentage' => $this->getPercentageByStatus(StatusType::STATUS_SUCCESS)
        ];
    }

    /**
     * Get how many tasks was failed
     */
    public function getFailedTasks(): array
    {
        return [
            'tasks' => array_values(
                array_filter($this->tasks, function (TaskType $task) {
                    return $task->getStatus()->isFailed();
                })
            ),
            'percentage' => $this->getPercentageByStatus(StatusType::STATUS_FAILED)
        ];
    }

    /**
     * Get how many tasks is actually running
     */
    public function getRunningTasks(): array
    {
        return [
            'tasks' => array_values(
                array_filter($this->tasks, function (TaskType $task) {
                    return $task->getStatus()->isRunning();
                })
            ),
            'percentage' => $this->getPercentageByStatus(StatusType::STATUS_RUNNING)
        ];
    }

    /**
     * Get how many tasks are in pending
     */
    public function getPendingTasks(): array
    {
        return [
            'tasks' => array_values(
                array_filter($this->tasks, function (TaskType $task) {
                    return $task->getStatus()->isPending();
                })
            ),
            'percentage' => $this->getPercentageByStatus(StatusType::STATUS_PENDING)
        ];
    }

    /**
     * Get how many task are in the given status compared to the number of existing tasks
     * @param string $status
     * @return float|int
     */
    private function getPercentageByStatus(string $status)
    {
        $tasks = count($this->tasks);
        $found = count(array_filter($this->tasks, function (TaskType $taskType) use ($status){
            return $taskType->getStatus()->getStatus() ===  $status;
        }));

        return ($found * 100) / $tasks;
    }

    /**
     * Finished means completed but can be failed or successful
     * @return array
     */
    public function getFinishedTasks(): array
    {
        $tasks = array_merge(
            array_values(
                array_filter($this->tasks, function (TaskType $task) {
                    return $task->getStatus()->isSuccess();
                })
            ),
            array_values(array_filter($this->tasks, function (TaskType $task) {
                return $task->getStatus()->isFailed();
            }))
        );
        return  [
            'tasks' => $tasks,
            'percentage' => $this->getCompletedTasksPercentage()
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTaskSummaryOnChannel(string $channel): array
    {
        $tasks = array_values(
            array_filter($this->tasks, function (TaskType $task) use ($channel){
                return $task->getType() === ChannelAbstract::getTypeFromChannelName($channel) ;
            })
        );

        return [
            'type' => ChannelAbstract::getTypeFromChannelName($channel),
            'tasks' => $tasks,
            'percentage' => $this->getProgressOnChannel($channel)
        ];
    }

    /**
     * Get the current progress on a specified channel
     * @param string $channel
     * @return ProgressType
     */
    public function getProgressOnChannel(string $channel): ProgressType
    {
        $failed = $completed = $success = 0;
        foreach ($this->tasks as $task) {
            if ($task->getType() === ChannelAbstract::getTypeFromChannelName($channel)) { //task is for the specified channel
                if($task->getStatus()->isSuccess() || $task->getStatus()->isFailed()) {
                    $completed ++;
                }
                if($task->getStatus()->isFailed()) {
                    $failed ++;
                } else if ($task->getStatus()->isSuccess()) {
                    $success ++;
                }
            }
        }

        return ProgressType::build(
            ($completed * 100) / $this->countTotalOrders,
            ($success * 100) / $this->countTotalOrders,
            ($failed * 100) / $this->countTotalOrders
        );
    }

    public function find(string $orderId)
    {
        $task = array_values(
            array_filter($this->tasks, function (TaskType $taskType) use ($orderId){
                return  $taskType->getUuid() === $orderId;
            })
        );

        return $task[0] ?? null;
    }

    private function assertParams(array $tasks)
    {
        foreach ($tasks as $task) {
            if (!$task instanceof TaskType) {
                throw new \InvalidArgumentException('Invalid task type');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'tasks' => $this->tasks,
            'percentage' => $this->percentage
        ];
    }

    /**
     * @return float|int
     */
    private function getCompletedTasksPercentage()
    {
        return $this->getPercentageByStatus(StatusType::STATUS_FAILED) + $this->getPercentageByStatus(StatusType::STATUS_SUCCESS);
    }
}
