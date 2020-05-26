<?php


namespace Ipedis\Rabbit\DTO\Type;


use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class SummaryType implements \JsonSerializable
{
    /**
     * @var int
     */
    private $total;

    /**
     * @var int
     */
    private $inPending;

    /**
     * @var int
     */
    private $dispatched;

    /**
     * @var int
     */
    private $completed;

    /**
     * @var int
     */
    private $successful;

    /**
     * @var int
     */
    private $failed;

    private function __construct(
        int $total,
        int $inPending,
        int $dispatched,
        int $completed,
        int $successful,
        int $failed
    )
    {
        $this->assertCounts($total, $inPending, $dispatched, $completed, $successful, $failed);
        $this->total = $total;
        $this->inPending = $inPending;
        $this->dispatched = $dispatched;
        $this->completed = $completed;
        $this->failed = $failed;
        $this->successful = $successful;
    }

    public static function build(
        int $total,
        int $inPending,
        int $dispatched,
        int $completed,
        int $successful,
        int $failed
    ) {
        return new self($total, $inPending, $dispatched, $completed, $successful, $failed);
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
    public function getInPending(): int
    {
        return $this->inPending;
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
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'total' => $this->getTotal(),
            'pending' => $this->getInPending(),
            'dispatched' => $this->getDispatched(),
            'completed' => $this->getCompleted(),
            'successful' => $this->getSuccessful(),
            'failed' => $this->getFailed()
        ];
    }

    /**
     * @param int $total
     * @param int $inPending
     * @param int $dispatched
     * @param int $completed
     * @param int $successful
     * @param int $failed
     * @throws InvalidProgressValueException
     */
    private function assertCounts(int $total, int $inPending, int $dispatched, int $completed, int $successful, int $failed): void
    {
        if ($total < 0 || $inPending < 0 || $dispatched < 0 || $completed < 0 || $successful < 0 || $failed < 0) {
            throw new InvalidProgressValueException('[SUMMARY] count should not be less than 0');
        }
    }
}
