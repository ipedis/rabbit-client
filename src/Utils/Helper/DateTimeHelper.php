<?php

declare(strict_types=1);

namespace Ipedis\Rabbit\Utils\Helper;

use DateTime;

trait DateTimeHelper
{
    protected function getCurrentDateTimeWithMicroseconds(): DateTime
    {
        $microseconds = sprintf('%.4f', microtime(true));
        $dateTime = DateTime::createFromFormat('U.u', $microseconds);

        if ($dateTime === false) {
            throw new \RuntimeException('Failed to create DateTime from microseconds format.');
        }

        return $dateTime;
    }

    protected function getDifferenceInMilliseconds(DateTime $startTime, DateTime $endTime): float
    {
        $microseconds = $this->getDifferenceWithMicroseconds($startTime, $endTime);

        return $microseconds * 1000;
    }

    protected function getDifferenceWithMicroseconds(DateTime $startTime, DateTime $endTime): float
    {
        $start = (float)$startTime->format('U.u');
        $end = (float)$endTime->format('U.u');

        if ($end < $start) {
            throw new \InvalidArgumentException('Startime is greater than endtime. Can not calculate difference.');
        }

        return (float)number_format(abs($end - $start), 6);
    }
}
