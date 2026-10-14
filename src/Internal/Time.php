<?php

declare(strict_types=1);

namespace GpxToolkit\Internal;

use DateTimeInterface;

/**
 * @internal
 */
final class Time
{
    /**
     * Unix time with microseconds.
     */
    public static function seconds(DateTimeInterface $time): float
    {
        return (float) $time->format('U.u');
    }
}
