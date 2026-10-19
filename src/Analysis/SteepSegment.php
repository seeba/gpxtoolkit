<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use GpxToolkit\Model\Point;

/**
 * A climb or descent within one line.
 */
final readonly class SteepSegment
{
    /**
     * @param int   $startPointIndex       index of the first point of the run within its line
     * @param int   $endPointIndex         index of the last point of the run within its line
     * @param float $elevationChangeMeters positive for climbs, negative for descents
     * @param float $gradePercent          average grade: elevation change divided by distance; negative for descents
     */
    public function __construct(
        public SteepSegmentType $type,
        public Point $start,
        public Point $end,
        public int $startPointIndex,
        public int $endPointIndex,
        public float $distanceMeters,
        public float $elevationChangeMeters,
        public float $gradePercent,
    ) {
    }
}
