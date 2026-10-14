<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Document;
use GpxToolkit\Model\Route;
use GpxToolkit\Model\Track;

/**
 * Splits a document into continuous lines. Gaps between segments, tracks and routes
 * are never bridged.
 */
final class Lines
{
    /**
     * @return list<Line>
     */
    public static function fromDocument(Document $document, Scope $scope = Scope::Tracks): array
    {
        $lines = [];
        if (Scope::Tracks === $scope) {
            foreach ($document->tracks as $index => $track) {
                array_push($lines, ...self::fromTrack($track, $index));
            }

            return $lines;
        }

        foreach ($document->routes as $index => $route) {
            $lines[] = self::fromRoute($route, $index);
        }

        return $lines;
    }

    /**
     * @return list<Line>
     */
    public static function fromTrack(Track $track, int $pathIndex = 0): array
    {
        $lines = [];
        foreach ($track->segments as $segmentIndex => $segment) {
            $lines[] = new Line(Scope::Tracks, $pathIndex, $segmentIndex, $segment->points);
        }

        return $lines;
    }

    public static function fromRoute(Route $route, int $pathIndex = 0): Line
    {
        return new Line(Scope::Routes, $pathIndex, 0, $route->points);
    }
}
