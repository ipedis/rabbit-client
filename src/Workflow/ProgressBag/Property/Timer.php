<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;

use DateTime;
use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;

class Timer implements \JsonSerializable
{
    private readonly float $spent;

    private readonly ?DateTime $startedAt;

    private readonly ?DateTime $finishedAt;

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
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    private function validateInputs(float $spent, ?DateTime $startedAt = null, ?DateTime $finishedAt = null): void
    {
        // validate duration
        $this->assertSpentTime($spent);

        // validate start and finish time
        $this->assertTime($startedAt, $finishedAt);
    }

    /**
     * @throws InvalidSpentTimeException
     */
    private function assertSpentTime(float $spent): void
    {
        if ($spent < 0) {
            throw new InvalidSpentTimeException($spent . ' is not a valid value for duration.');
        }
    }

    /**
     * @throws InvalidTimeException
     */
    private function assertTime(?DateTime $startedAt = null, ?DateTime $finishedAt = null): void
    {
        if ($startedAt instanceof \DateTime && $finishedAt instanceof \DateTime && $startedAt->getTimestamp() > $finishedAt->getTimestamp()) {
            throw new InvalidTimeException('Start time is greater than end time. Unless you have "time stone" with you, its not possible.');
        }
    }

    /**
     * @param float $spent - Duration in milliseconds
     * @param DateTime|null $startedAt - Timestamp for start time with microseconds
     * @param DateTime|null $finishedAt - Timestamp for end time with microseconds
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public static function build(float $spent, ?DateTime $startedAt = null, ?DateTime $finishedAt = null): self
    {
        return new self($spent, $startedAt, $finishedAt);
    }

    public function jsonSerialize(): array
    {
        return [
            'spent' => $this->getSpent(),
            'spentTime' => sprintf('%s ms', $this->getSpent() * 1000),
            'startedAt' => ($this->getStartedAt() instanceof \DateTime) ? $this->getStartedAt()->format('U.u') : null,
            'finishedAt' => ($this->getFinishedAt() instanceof \DateTime) ? $this->getFinishedAt()->format('U.u') : null
        ];
    }

    public function getSpent(): float
    {
        return $this->spent;
    }

    public function getStartedAt(): ?DateTime
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTime
    {
        return $this->finishedAt;
    }
}
