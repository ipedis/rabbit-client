<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag;

use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class Summary implements \JsonSerializable
{
    private readonly int $total;

    private readonly int $pending;

    private readonly int $dispatched;

    private readonly int $completed;

    private readonly int $successful;

    private readonly int $failed;

    /**
     * Summary constructor.
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
     * @throws InvalidProgressValueException
     */
    private function validateInputs(int $total, int $pending, int $dispatched, int $completed, int $successful, int $failed): void
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

    /**
     * @throws InvalidProgressValueException
     */
    public static function build(int $total, int $pending, int $dispatched, int $completed, int $successful, int $failed): self
    {
        return new self($total, $pending, $dispatched, $completed, $successful, $failed);
    }

    /**
     * @return array{total: int, pending: int, dispatched: int, completed: int, successful: int, failed: int}
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

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPending(): int
    {
        return $this->pending;
    }

    public function getDispatched(): int
    {
        return $this->dispatched;
    }

    public function getCompleted(): int
    {
        return $this->completed;
    }

    public function getSuccessful(): int
    {
        return $this->successful;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }
}
