<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

/**
 * One point of an elevation profile.
 */
final readonly class ProfilePoint
{
    /**
     * @param float      $distanceMeters  cumulative distance from the first point of the profile; gaps between lines are not counted
     * @param float|null $elevationMeters null when the point has no elevation
     * @param int        $pathIndex       index of the track or route in the document
     * @param int        $segmentIndex    index of the segment within the track; 0 for routes
     * @param int        $pointIndex      index of the point within its segment or route
     */
    public function __construct(
        public float $distanceMeters,
        public ?float $elevationMeters,
        public int $pathIndex,
        public int $segmentIndex,
        public int $pointIndex,
    ) {
    }
}
