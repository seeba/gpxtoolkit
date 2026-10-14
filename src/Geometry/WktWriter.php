<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Point;
use GpxToolkit\Model\Track;

/**
 * Writes WKT geometry in longitude/latitude order, without SRID.
 */
final class WktWriter
{
    public function point(Point $point): string
    {
        return sprintf('POINT(%F %F)', $point->longitude, $point->latitude);
    }

    /**
     * @param list<Point> $points
     */
    public function lineString(array $points): string
    {
        return 'LINESTRING('.implode(', ', array_map(
            static fn (Point $point): string => $point->longitude.' '.$point->latitude,
            $points,
        )).')';
    }

    /**
     * All points of the track as a single line.
     */
    public function trackLineString(Track $track): string
    {
        $points = [];
        foreach ($track->segments as $segment) {
            array_push($points, ...$segment->points);
        }

        return $this->lineString($points);
    }
}
