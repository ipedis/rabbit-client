<?php


namespace Ipedis\Rabbit\Workflow;


class ProgressBag
{
    /**
     * @var float
     */
    private $total;
    /**
     * @var float
     */
    private $done;

    public function __construct(float $total, float $done)
    {
        $this->total = $total;
        $this->done = $done;
    }

    public function getPourcentage(): float
    {
        return $this->total / $this->done * 100;
    }

    /**
     * @return float
     */
    public function getTotal(): float
    {
        return $this->total;
    }

    /**
     * @return float
     */
    public function getDone(): float
    {
        return $this->done;
    }


}
