<?php
namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class Summary implements \JsonSerializable
{
    /**
     * @var int
     */
    private int $total;
    /**
     * @var int
     */
    private int $pending;
    /**
     * @var int
     */
    private int $dispatched;
    /**
     * @var int
     */
    private int $completed;
    /**
     * @var int
     */
    private int $successful;
    /**
     * @var int
     */
    private int $failed;

    /**
     * Summary constructor.
     * @param int $total
     * @param int $pending
     * @param int $dispatched
     * @param int $completed
     * @param int $successful
     * @param int $failed
     * @throws InvalidProgressValueException
     */
    private function __construct(int $total, int $pending, int $dispatched, int $completed, int $successful, int $failed)
    {
        $this->validateInputs($total, $pending, $dispatched, $completed, $successful, $failed);
        $this->total = $total;
        $this->pending = $pending;
        $this->dispatched = $dispatched;
        $this->completed = $completed;
        $this->successful = $successful;
        $this->failed = $failed;
    }

    /**
     * @param int $total
     * @param int $pending
     * @param int $dispatched
     * @param int $completed
     * @param int $successful
     * @param int $failed
     * @return Summary
     * @throws InvalidProgressValueException
     */
    public static function build(int $total, int $pending, int $dispatched, int $completed, int $successful, int $failed): self
    {
        return new self($total, $pending, $dispatched, $completed, $successful, $failed);
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
    public function getPending(): int
    {
        return $this->pending;
    }

    /**
     * @return int
     */
    public function getDispatched(): int
    {
        return $this->dispatched;
    }

    /**
     * @return int
     */
    public function getCompleted(): int
    {
        return $this->completed;
    }

    /**
     * @return int
     */
    public function getSuccessful(): int
    {
        return $this->successful;
    }

    /**
     * @return int
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->getTotal(),
            'pending' => $this->getPending(),
            'dispatched' => $this->getDispatched(),
            'completed' => $this->getCompleted(),
            'successful' => $this->getSuccessful(),
            'failed' => $this->getFailed()
        ];
    }

    /**
     * @param int $total
     * @param int $pending
     * @param int $dispatched
     * @param int $completed
     * @param int $successful
     * @param int $failed
     * @throws InvalidProgressValueException
     */
    private function validateInputs(int $total, int $pending, int $dispatched, int $completed, int $successful, int $failed)
    {
        $arguments = func_get_args();
        foreach ($arguments as $argument) {
            if ($argument < 0) {
                throw new InvalidProgressValueException('Number of orders can not be less than 0.');
            }
        }

        if ($total < $pending || $total < $dispatched || $total < $completed || $total < $successful || $total < $failed) {
            throw new InvalidProgressValueException('Total number of orders must always be the highest value.');
        }

        if ($completed !== ($successful + $failed)) {
            throw new InvalidProgressValueException('Inconsistent value of completed, successful and failed orders.');
        }
    }
}