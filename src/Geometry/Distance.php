<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Point;

final class Distance
{
    public const float EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Great-circle distance between two points (haversine on a sphere), in meters.
     */
    public static function haversineMeters(Point $from, Point $to): float
    {
        $dLat = deg2rad($to->latitude - $from->latitude);
        $dLon = deg2rad($to->longitude - $from->longitude);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($from->latitude)) * cos(deg2rad($to->latitude)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * atan2(sqrt($a), sqrt(1 - $a));
    }
}
