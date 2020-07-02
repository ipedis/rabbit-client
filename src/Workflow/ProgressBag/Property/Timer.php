<?php


namespace Ipedis\Rabbit\Workflow\ProgressBag\Property;


use Ipedis\Rabbit\Exception\Timer\InvalidSpentTimeException;
use Ipedis\Rabbit\Exception\Timer\InvalidTimeException;

class Timer implements \JsonSerializable
{
    private float $spent;
    private ?float $startedAt;
    private ?float $finishedAt;

    /**
     * Timer constructor.
     * @param float $spent - Duration in milliseconds
     * @param float|null $startedAt - Timestamp for start time with microseconds
     * @param float|null $finishedAt - Timestamp for end time with microseconds
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    private function __construct(float $spent, ?float $startedAt = null, ?float $finishedAt = null)
    {
        $this->validateInputs($spent, $startedAt, $finishedAt);
        $this->spent = $spent;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }

    /**
     * @param float $spent - Duration in milliseconds
     * @param float|null $startedAt - Timestamp for start time with microseconds
     * @param float|null $finishedAt - Timestamp for end time with microseconds
     * @return Timer
     * @throws InvalidSpentTimeException
     * @throws InvalidTimeException
     */
    public static function build(float $spent, ?float $startedAt = null, ?float $finishedAt = null): self
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
     * @return float|null
     */
    public function getStartedAt(): ?float
    {
        return $this->startedAt;
    }

    /**
     * @return float|null
     */
    public function getFinishedAt(): ?float
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
            'startedAt' => $this->getStartedAt(),
            'finishedAt' => $this->getFinishedAt()
        ];
    }

    /**
     * @param float $spent
     * @param float|null $startedAt
     * @param float|null $finishedAt
     * @throws InvalidTimeException
     * @throws InvalidSpentTimeException
     */
    private function validateInputs(float $spent, ?float $startedAt = null, ?float $finishedAt = null)
    {
        // validate duration
        $this->assertSpentTime($spent);

        // validate start and finish time
        $this->assertTime($startedAt, $finishedAt);
    }
    /**
     * @param float|null $startedAt
     * @param float|null $finishedAt
     * @throws InvalidTimeException
     */
    private function assertTime(?float $startedAt = null, ?float $finishedAt = null)
    {
        if ($startedAt === null && $finishedAt === null) {
            return;
        }

        if ($startedAt > $finishedAt) {
            throw new InvalidTimeException('[TIMER] Start time is greater than end time. Unless you have "time stone" with you, its not possible.');
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