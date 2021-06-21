<?php

namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;

use DateTime;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;

class Timer implements \JsonSerializable
{
    /**
     * @var float
     */
    private float $spent;
    /**
     * @var DateTime|null
     */
    private ?DateTime $startedAt;
    /**
     * @var DateTime|null
     */
    private ?DateTime $finishedAt;

    /**
     * Timer constructor.
     * @param float $spent - Duration in milliseconds
     * @param DateTime|null $startedAt - Timestamp for start time with microseconds
     * @param DateTime|null $finishedAt - Timestamp for end time with microseconds
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    private function __construct(float $spent, ?DateTime $startedAt = null, ?DateTime $finishedAt = null)
    {
        $this->validateInputs($spent, $startedAt, $finishedAt);
        $this->spent = $spent;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }

    /**
     * @param float $spent - Duration in milliseconds
     * @param DateTime|null $startedAt - Timestamp for start time with microseconds
     * @param DateTime|null $finishedAt - Timestamp for end time with microseconds
     * @return Timer
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public static function build(float $spent, ?DateTime $startedAt = null, ?DateTime $finishedAt = null): self
    {
        return new self($spent, $startedAt, $finishedAt);
    }

    /**
     * @return float
     */
    public function getSpent(): float
    {
        return $this->spent;
    }

    /**
     * @return DateTime|null
     */
    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    /**
     * @return DateTime|null
     */
    public function getFinishedAt(): ?DateTime
    {
        return $this->finishedAt;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'spent' => $this->getSpent(),
            'spentTime' => sprintf('%s ms', $this->getSpent()*1000),
            'startedAt' => (null !== $this->getStartedAt()) ? $this->getStartedAt()->format('U.u') : null,
            'finishedAt' => (null !== $this->getFinishedAt()) ? $this->getFinishedAt()->format('U.u') : null
        ];
    }

    /**
     * @param float $spent
     * @param DateTime|null $startedAt
     * @param DateTime|null $finishedAt
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    private function validateInputs(float $spent, ?DateTime $startedAt = null, ?DateTime $finishedAt = null)
    {
        // validate duration
        $this->assertSpentTime($spent);

        // validate start and finish time
        $this->assertTime($startedAt, $finishedAt);
    }

    /**
     * @param DateTime|null $startedAt
     * @param DateTime|null $finishedAt
     * @throws InvalidTimeException
     */
    private function assertTime(?DateTime $startedAt = null, ?DateTime $finishedAt = null)
    {
        if ($startedAt !== null && $finishedAt !== null) {
            if ($startedAt->getTimestamp() > $finishedAt->getTimestamp()) {
                throw new InvalidTimeException('Start time is greater than end time. Unless you have "time stone" with you, its not possible.');
            }
        }
    }

    /**
     * @param float $spent
     * @throws InvalidSpentTimeException
     */
    private function assertSpentTime(float $spent)
    {
        if ($spent < 0) {
            throw new InvalidSpentTimeException("{$spent} is not a valid value for duration.");
        }
    }
}
