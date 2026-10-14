<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Bounds;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Point;

final class BoundsCalculator
{
    /**
     * Bounds of all points in the scope; null when the scope has no points.
     */
    public function calculate(Document $document, Scope $scope = Scope::Tracks): ?Bounds
    {
        $lines = Lines::fromDocument($document, $scope);

        return $this->fromPoints((static function () use ($lines): iterable {
            foreach ($lines as $line) {
                yield from $line->points;
            }
        })());
    }

    /**
     * @param iterable<Point> $points
     */
    public function fromPoints(iterable $points): ?Bounds
    {
        $minLatitude = INF;
        $maxLatitude = -INF;
        $minLongitude = INF;
        $maxLongitude = -INF;

        foreach ($points as $point) {
            $minLatitude = min($minLatitude, $point->latitude);
            $maxLatitude = max($maxLatitude, $point->latitude);
            $minLongitude = min($minLongitude, $point->longitude);
            $maxLongitude = max($maxLongitude, $point->longitude);
        }

        if (INF === $minLatitude) {
            return null;
        }

        return new Bounds($minLatitude, $minLongitude, $maxLatitude, $maxLongitude);
    }
}
