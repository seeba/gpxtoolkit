<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Utils;

use GpxToolkit\Services\Model\Gpx\Point;

class ReduceGPXPoints
{
    /**
     * @param Point[] $points
     * @return Point[]
     */
    public static function reducePoints(array $points, float $epsilon): array
    {
        $n = count($points);
        if ($n < 3) {
            return $points;
        }

        $firstPoint = $points[0];
        $lastPoint = $points[$n - 1];

        $isLoop = self::distanceBetweenPoints($firstPoint, $lastPoint) < $epsilon * 10;

        if ($isLoop) {
            $index = -1;
            $maxDistance = 0;

            for ($i = 1; $i < $n - 1; $i++) {
                $distance = self::distanceBetweenPoints($points[$i], $firstPoint);
                if ($distance > $maxDistance) {
                    $index = $i;
                    $maxDistance = $distance;
                }
            }

            if ($index > 0 && $maxDistance > $epsilon) {
                $firstHalf = self::reducePoints(array_slice($points, 0, $index + 1), $epsilon);
                $secondHalf = self::reducePoints(array_slice($points, $index), $epsilon);

                return array_merge(array_slice($firstHalf, 0, -1), $secondHalf);
            }
        }

        $index = -1;
        $maxDistance = 0;

        for ($i = 1; $i < $n - 1; $i++) {
            $distance = self::perpendicularDistance($points[$i], $firstPoint, $lastPoint);
            if ($distance > $maxDistance) {
                $index = $i;
                $maxDistance = $distance;
            }
        }

        if ($maxDistance > $epsilon) {
            $leftPoints = self::reducePoints(array_slice($points, 0, $index + 1), $epsilon);
            $rightPoints = self::reducePoints(array_slice($points, $index), $epsilon);

            return array_merge(array_slice($leftPoints, 0, -1), $rightPoints);
        } else {
            return [$firstPoint, $lastPoint];
        }
    }

    private static function perpendicularDistance(Point $point, Point $firstPoint, Point $lastPoint): float
    {
        $x0 = $point->latitude;
        $y0 = $point->longitude;
        $x1 = $firstPoint->latitude;
        $y1 = $firstPoint->longitude;
        $x2 = $lastPoint->latitude;
        $y2 = $lastPoint->longitude;

        $numerator = abs(($y2 - $y1) * $x0 - ($x2 - $x1) * $y0 + $x2 * $y1 - $y2 * $x1);
        $denominator = sqrt(pow($y2 - $y1, 2) + pow($x2 - $x1, 2));

        return $denominator == 0 ? 0 : $numerator / $denominator;
    }

    private static function distanceBetweenPoints(Point $point1, Point $point2): float
    {
        $lat1 = $point1->latitude;
        $lon1 = $point1->longitude;
        $lat2 = $point2->latitude;
        $lon2 = $point2->longitude;

        return sqrt(pow($lat2 - $lat1, 2) + pow($lon2 - $lon1, 2));
    }
}
