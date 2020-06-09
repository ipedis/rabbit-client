<?php


namespace Ipedis\Rabbit\DTO\Type;


use Ipedis\Rabbit\Exception\Progress\InvalidProgressValueException;

class ProgressType implements \JsonSerializable
{
    /**
     * @var float
     */
    private $completed;

    /**
     * @var float
     */
    private $success;

    /**
     * @var float
     */
    private $failed;

    private function __construct(float $completed, float $success, float $failed)
    {
        $this->assertProgress($completed, $success, $failed);
        $this->completed = $completed;
        $this->success = $success;
        $this->failed = $failed;
    }

    public static function build(float $completed, float $success, float $failed)
    {
        return new self($completed, $success, $failed);
    }

    /**
     * @return float
     */
    public function getCompleted(): float
    {
        return $this->completed;
    }

    /**
     * @return float
     */
    public function getSuccess(): float
    {
        return $this->success;
    }

    /**
     * @return float
     */
    public function getFailed(): float
    {
        return $this->failed;
    }

    public function jsonSerialize()
    {
        return [
            'completed' => $this->getCompleted(),
            'success' => $this->getSuccess(),
            'failed' => $this->getFailed()
        ];
    }

    /**
     * @param float $completed
     * @param float $success
     * @param float $failed
     * @throws InvalidProgressValueException
     */
    private function assertProgress(float $completed, float $success, float $failed): void
    {
        if ($completed > 100 || $success > 100 || $failed > 100 || $completed < 0 || $success < 0 || $failed < 0) {
            throw new InvalidProgressValueException('[PROGRESS] Invalid progress value');
        }
    }
}
