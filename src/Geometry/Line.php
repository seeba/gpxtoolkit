<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Point;

/**
 * A continuous sequence of points: one track segment or one route.
 * Points keep the order of the document.
 */
final readonly class Line
{
    /**
     * @param int         $pathIndex    index of the track or route in the document
     * @param int         $segmentIndex index of the segment within the track; 0 for routes
     * @param list<Point> $points
     */
    public function __construct(
        public Scope $scope,
        public int $pathIndex,
        public int $segmentIndex,
        public array $points,
    ) {
    }
}
