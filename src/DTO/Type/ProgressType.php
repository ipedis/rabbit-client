<?php


namespace Ipedis\Rabbit\DTO\Type;


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
}
