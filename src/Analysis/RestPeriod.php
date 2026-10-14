<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use DateTimeImmutable;

/**
 * A run of consecutive slow point pairs within one line.
 */
final readonly class RestPeriod
{
    /**
     * @param int $startPointIndex index of the first point of the run within its line
     * @param int $endPointIndex   index of the last point of the run within its line
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public float $durationSeconds,
        public float $distanceMeters,
        public int $startPointIndex,
        public int $endPointIndex,
    ) {
    }
}
