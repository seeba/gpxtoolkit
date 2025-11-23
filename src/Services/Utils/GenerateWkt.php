<?php

namespace GpxToolkit\Services\Utils;

use GpxToolkit\Services\Model\Gpx\Point;

class GenerateWkt
{
    /**
     * @param Point[] $points
     * @return string
     */
    public static function generateWktLineString(array $points): string
    {
        $coordinates = array_map(function (Point $point) {
            return $point->longitude . ' ' . $point->latitude;
        }, $points);

        return 'LINESTRING(' . implode(', ', $coordinates) . ')';
    }

    /**
     * @param Point[] $points
     * @return Point[]
     */
    public static function generatePointsWkt(array $points): array
    {
        $pointsWkt = [];

        foreach ($points as $point) {
            $pointsWkt[] = Point::create(
                $point->latitude,
                $point->longitude,
                $point->elevation,
                $point->time,
                $point->magVar,
                $point->geoidHeight,
                $point->name,
                $point->comment,
                $point->description,
                $point->source,
                $point->links,
                $point->symbol,
                $point->type,
                $point->fix,
                $point->satelitesQuantity,
                $point->hdop,
                $point->vdop,
                $point->pdop,
                $point->ageOfGpsData,
                $point->dgsid,
                $point->extensions,
                sprintf('POINT(%F %F)', $point->longitude, $point->latitude)
            );
        }
        return $pointsWkt;
    }
}