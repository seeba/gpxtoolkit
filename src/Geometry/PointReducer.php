<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Point;
use InvalidArgumentException;

/**
 * Ramer–Douglas–Peucker simplification with the tolerance in meters.
 *
 * Distances are measured in a local equirectangular projection of the line (longitude
 * scaled by the cosine of the mean latitude), accurate for lines up to hundreds of
 * kilometers. A point is compared with the segment between the kept ends, not with
 * the infinite line through them, so loops and out-and-back paths keep their shape.
 * The first and last point are always kept and the order is never changed.
 */
final readonly class PointReducer
{
    public const float DEFAULT_TOLERANCE_METERS = 1.0;

    public function __construct(
        public float $toleranceMeters = self::DEFAULT_TOLERANCE_METERS,
    ) {
        if (!is_finite($toleranceMeters) || $toleranceMeters < 0) {
            throw new InvalidArgumentException('Tolerance must be a non-negative number of meters.');
        }
    }

    /**
     * @param list<Point> $points points of one continuous line
     *
     * @return list<Point>
     */
    public function reduce(array $points): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        [$x, $y] = $this->project($points);

        $keep = array_fill(0, $count, false);
        $keep[0] = true;
        $keep[$count - 1] = true;

        // Iterative to keep the call stack flat for long lines.
        $ranges = [[0, $count - 1]];
        while ([] !== $ranges) {
            [$first, $last] = array_pop($ranges);

            $index = -1;
            $maxDistance = 0.0;
            for ($i = $first + 1; $i < $last; ++$i) {
                $distance = self::segmentDistance($x[$i], $y[$i], $x[$first], $y[$first], $x[$last], $y[$last]);
                if ($distance > $maxDistance) {
                    $index = $i;
                    $maxDistance = $distance;
                }
            }

            if ($index > 0 && $maxDistance > $this->toleranceMeters) {
                $keep[$index] = true;
                $ranges[] = [$index, $last];
                $ranges[] = [$first, $index];
            }
        }

        $reduced = [];
        foreach ($points as $i => $point) {
            if ($keep[$i]) {
                $reduced[] = $point;
            }
        }

        return $reduced;
    }

    /**
     * Projects points to meters relative to the first point.
     *
     * @param non-empty-list<Point> $points
     *
     * @return array{list<float>, list<float>}
     */
    private function project(array $points): array
    {
        $latitudeSum = 0.0;
        foreach ($points as $point) {
            $latitudeSum += $point->latitude;
        }
        $metersPerDegree = deg2rad(1) * Distance::EARTH_RADIUS_METERS;
        $longitudeScale = cos(deg2rad($latitudeSum / count($points))) * $metersPerDegree;

        $origin = $points[0];
        $x = [];
        $y = [];
        foreach ($points as $point) {
            // Shortest way around, so lines crossing the 180th meridian stay continuous.
            $longitudeDelta = fmod($point->longitude - $origin->longitude + 540.0, 360.0) - 180.0;
            $x[] = $longitudeDelta * $longitudeScale;
            $y[] = ($point->latitude - $origin->latitude) * $metersPerDegree;
        }

        return [$x, $y];
    }

    private static function segmentDistance(float $px, float $py, float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx ** 2 + $dy ** 2;
        $t = 0.0 === $lengthSquared
            ? 0.0
            : max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / $lengthSquared));

        return hypot($px - ($ax + $t * $dx), $py - ($ay + $t * $dy));
    }
}
