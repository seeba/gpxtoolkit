<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use GpxToolkit\Geometry\Distance;
use GpxToolkit\Geometry\Line;
use GpxToolkit\Geometry\Lines;
use GpxToolkit\Geometry\Scope;
use GpxToolkit\Model\Document;

/**
 * Builds a continuous elevation profile in document order. Every point is included;
 * segment and route boundaries are kept in the indexes of each profile point.
 */
final class ElevationProfileBuilder
{
    /**
     * @return list<ProfilePoint>
     */
    public function build(Document $document, Scope $scope = Scope::Tracks): array
    {
        return $this->buildFromLines(Lines::fromDocument($document, $scope));
    }

    /**
     * @param list<Line> $lines
     *
     * @return list<ProfilePoint>
     */
    public function buildFromLines(array $lines): array
    {
        $profile = [];
        $distance = 0.0;

        foreach ($lines as $line) {
            $previous = null;
            foreach ($line->points as $index => $point) {
                if (null !== $previous) {
                    $distance += Distance::haversineMeters($previous, $point);
                }

                $profile[] = new ProfilePoint($distance, $point->elevation, $line->pathIndex, $line->segmentIndex, $index);
                $previous = $point;
            }
        }

        return $profile;
    }
}
