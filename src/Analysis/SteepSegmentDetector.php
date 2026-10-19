<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use GpxToolkit\Geometry\Distance;
use GpxToolkit\Model\Point;
use InvalidArgumentException;

/**
 * Finds climbs and descents: runs of consecutive point pairs whose grade is at least
 * the threshold in the same direction, over at least the minimum distance.
 *
 * Only pairs where both points have an elevation and the points are apart are considered.
 * A pair without elevation or with zero distance ends the current run.
 */
final readonly class SteepSegmentDetector
{
    public const float DEFAULT_GRADE_THRESHOLD_PERCENT = 10.0;
    public const float DEFAULT_MIN_DISTANCE_METERS = 50.0;

    public function __construct(
        public float $gradeThresholdPercent = self::DEFAULT_GRADE_THRESHOLD_PERCENT,
        public float $minDistanceMeters = self::DEFAULT_MIN_DISTANCE_METERS,
    ) {
        if (!is_finite($gradeThresholdPercent) || $gradeThresholdPercent <= 0) {
            throw new InvalidArgumentException('Grade threshold must be a positive number.');
        }
        if (!is_finite($minDistanceMeters) || $minDistanceMeters < 0) {
            throw new InvalidArgumentException('Minimum steep segment distance must be a non-negative number.');
        }
    }

    /**
     * @param list<Point> $points points of one continuous line
     *
     * @return list<SteepSegment>
     */
    public function detect(array $points): array
    {
        $segments = [];
        $runType = null;
        $runStart = 0;
        $runDistance = 0.0;
        $runElevation = 0.0;

        foreach ($points as $index => $point) {
            if (0 === $index) {
                continue;
            }

            $previous = $points[$index - 1];
            $type = null;
            $distance = 0.0;
            $elevationChange = 0.0;
            if (null !== $previous->elevation && null !== $point->elevation) {
                $distance = Distance::haversineMeters($previous, $point);
                $elevationChange = $point->elevation - $previous->elevation;
                if ($distance > 0 && abs($elevationChange / $distance) * 100 >= $this->gradeThresholdPercent) {
                    $type = $elevationChange > 0 ? SteepSegmentType::Climb : SteepSegmentType::Descent;
                }
            }

            if (null !== $type && $type === $runType) {
                $runDistance += $distance;
                $runElevation += $elevationChange;

                continue;
            }

            $this->close($segments, $points, $runType, $runStart, $index - 1, $runDistance, $runElevation);
            $runType = $type;
            $runStart = $index - 1;
            $runDistance = $distance;
            $runElevation = $elevationChange;
        }

        $this->close($segments, $points, $runType, $runStart, count($points) - 1, $runDistance, $runElevation);

        return $segments;
    }

    /**
     * @param list<SteepSegment> $segments
     * @param list<Point>        $points
     */
    private function close(
        array &$segments,
        array $points,
        ?SteepSegmentType $type,
        int $start,
        int $end,
        float $distance,
        float $elevationChange,
    ): void {
        if (null === $type || $distance < $this->minDistanceMeters) {
            return;
        }

        $segments[] = new SteepSegment(
            $type,
            $points[$start],
            $points[$end],
            $start,
            $end,
            $distance,
            $elevationChange,
            $elevationChange / $distance * 100,
        );
    }
}
