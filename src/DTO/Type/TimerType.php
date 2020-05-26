<?php


namespace Ipedis\Rabbit\DTO\Type;


class TimerType implements \JsonSerializable
{
    /**
     * @var float
     */
    private $spent;

    /**
     * @var \DateTime|null
     */
    private $startedAt;

    /**
     * @var \DateTime|null
     */
    private $finishedAt;

    private function __construct(float $spent, ?\DateTime $startedAt = null, ?\DateTime $finishedAt = null)
    {
        $this->spent = $spent;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }

    public static function build(float $spent, ?\DateTime $startedAt = null, ?\DateTime $finishedAt = null)
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
     * @return \DateTime|null
     */
    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'spent' => $this->getSpent(),
            'startedAt'     => (!is_null($this->getStartedAt())) ? $this->getStartedAt()->getTimestamp() : null,
            'finishedAt'    => (!is_null($this->getFinishedAt())) ? $this->getFinishedAt()->getTimestamp() : null,
        ];
    }
}
