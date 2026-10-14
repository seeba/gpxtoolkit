<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Geometry\Distance;
use GpxToolkit\Model\Point;

/**
 * Finds climbs and descents whose grade exceeds the threshold over more than the minimum distance.
 * Requires every point to have an elevation.
 */
final readonly class SteepSegmentDetector
{
    public function __construct(
        private float $gradeThresholdPercent = 10.0,
        private float $minDistanceMeters = 50.0,
    ) {
    }

    /**
     * @param list<Point> $points
     *
     * @return list<array{type: string, start: Point, end: Point, distanceMeters: float, elevationChangeMeters: float, gradePercent: float}>
     */
    public function detect(array $points): array
    {
        $segments = [];
        $current = null;
        $previous = null;
        $previousElevation = null;

        foreach ($points as $index => $point) {
            if (null === $point->elevation) {
                throw new ValidationException(sprintf('Steep segment detection requires elevation of every point, point %d has none.', $index));
            }

            if (null !== $previous) {
                $distance = Distance::haversineMeters($previous, $point);
                $elevationDiff = $point->elevation - $previousElevation;
                $grade = $distance > 0 ? ($elevationDiff / $distance) * 100 : 0.0;

                if (abs($grade) >= $this->gradeThresholdPercent) {
                    $type = $grade > 0 ? 'climb' : 'descent';

                    if (null === $current || $current['type'] !== $type) {
                        if (null !== $current) {
                            $this->close($segments, $current, $previous);
                        }
                        $current = ['type' => $type, 'start' => $previous, 'distance' => $distance, 'elevation' => $elevationDiff];
                    } else {
                        $current['distance'] += $distance;
                        $current['elevation'] += $elevationDiff;
                    }
                } elseif (null !== $current) {
                    $this->close($segments, $current, $previous);
                    $current = null;
                }
            }

            $previous = $point;
            $previousElevation = $point->elevation;
        }

        if (null !== $current) {
            $this->close($segments, $current, $previous);
        }

        return $segments;
    }

    /**
     * @param list<array{type: string, start: Point, end: Point, distanceMeters: float, elevationChangeMeters: float, gradePercent: float}> $segments
     * @param array{type: string, start: Point, distance: float, elevation: float}                                                         $current
     */
    private function close(array &$segments, array $current, Point $end): void
    {
        if ($current['distance'] <= $this->minDistanceMeters) {
            return;
        }

        $segments[] = [
            'type' => $current['type'],
            'start' => $current['start'],
            'end' => $end,
            'distanceMeters' => $current['distance'],
            'elevationChangeMeters' => $current['elevation'],
            'gradePercent' => ($current['elevation'] / $current['distance']) * 100,
        ];
    }
}
