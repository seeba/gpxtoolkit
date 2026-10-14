<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Point;

/**
 * Ramer–Douglas–Peucker simplification with special handling of closed loops.
 */
final readonly class PointReducer
{
    public function __construct(
        private float $toleranceDegrees = 0.00001,
    ) {
    }

    /**
     * @param list<Point> $points
     *
     * @return list<Point>
     */
    public function reduce(array $points): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        $first = $points[0];
        $last = $points[$count - 1];

        if ($this->planarDistance($first, $last) < $this->toleranceDegrees * 10) {
            $index = -1;
            $maxDistance = 0.0;

            for ($i = 1; $i < $count - 1; ++$i) {
                $distance = $this->planarDistance($points[$i], $first);
                if ($distance > $maxDistance) {
                    $index = $i;
                    $maxDistance = $distance;
                }
            }

            if ($index > 0 && $maxDistance > $this->toleranceDegrees) {
                return $this->split($points, $index);
            }
        }

        $index = -1;
        $maxDistance = 0.0;

        for ($i = 1; $i < $count - 1; ++$i) {
            $distance = $this->perpendicularDistance($points[$i], $first, $last);
            if ($distance > $maxDistance) {
                $index = $i;
                $maxDistance = $distance;
            }
        }

        if ($maxDistance > $this->toleranceDegrees) {
            return $this->split($points, $index);
        }

        return [$first, $last];
    }

    /**
     * @param list<Point> $points
     *
     * @return list<Point>
     */
    private function split(array $points, int $index): array
    {
        $left = $this->reduce(array_slice($points, 0, $index + 1));
        $right = $this->reduce(array_slice($points, $index));

        return [...array_slice($left, 0, -1), ...$right];
    }

    private function perpendicularDistance(Point $point, Point $first, Point $last): float
    {
        $dx = $last->latitude - $first->latitude;
        $dy = $last->longitude - $first->longitude;
        $denominator = sqrt($dx ** 2 + $dy ** 2);
        if (0.0 === $denominator) {
            return 0.0;
        }

        return abs(
            $dy * $point->latitude
            - $dx * $point->longitude
            + $last->latitude * $first->longitude
            - $last->longitude * $first->latitude,
        ) / $denominator;
    }

    private function planarDistance(Point $a, Point $b): float
    {
        return sqrt(($b->latitude - $a->latitude) ** 2 + ($b->longitude - $a->longitude) ** 2);
    }
}
