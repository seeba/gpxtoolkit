<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Bounds;
use GpxToolkit\Model\Document;

final class BoundsCalculator
{
    /**
     * Bounds of all track points; null when the document has no track points.
     */
    public function calculate(Document $document): ?Bounds
    {
        $minLatitude = INF;
        $maxLatitude = -INF;
        $minLongitude = INF;
        $maxLongitude = -INF;

        foreach ($document->tracks as $track) {
            foreach ($track->segments as $segment) {
                foreach ($segment->points as $point) {
                    $minLatitude = min($minLatitude, $point->latitude);
                    $maxLatitude = max($maxLatitude, $point->latitude);
                    $minLongitude = min($minLongitude, $point->longitude);
                    $maxLongitude = max($maxLongitude, $point->longitude);
                }
            }
        }

        if (INF === $minLatitude) {
            return null;
        }

        return new Bounds($minLatitude, $minLongitude, $maxLatitude, $maxLongitude);
    }
}
