<?php

namespace Ipedis\Rabbit\Utils\Helper;

use DateTime;

trait DateTimeHelper
{
    /**
     * @return DateTime
     */
    protected function getCurrentDateTimeWithMicroseconds(): DateTime
    {
        $microseconds = sprintf('%.4f', microtime(true));

        return DateTime::createFromFormat('U.u', $microseconds);
    }

    /**
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return float
     */
    protected function getDifferenceInMilliseconds(DateTime $startTime, DateTime $endTime): float
    {
        $microseconds = $this->getDifferenceWithMicroseconds($startTime, $endTime);

        return $microseconds * 1000;
    }

    /**
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return float
     */
    protected function getDifferenceWithMicroseconds(DateTime $startTime, DateTime $endTime): float
    {
        $start = (float)$startTime->format('U.u');
        $end = (float)$endTime->format('U.u');

        if ($end < $start) {
            throw new \InvalidArgumentException('Startime is greater than endtime. Can not calculate difference.');
        }

        return number_format(abs($end - $start), 6);
    }
}
