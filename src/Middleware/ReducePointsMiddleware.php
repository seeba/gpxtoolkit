<?php

declare(strict_types=1);

namespace GpxToolkit\Middleware;

use GpxToolkit\Geometry\PointReducer;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Track;
use GpxToolkit\Model\TrackSegment;

/**
 * Simplifies every track segment. Other document data is kept unchanged.
 */
final readonly class ReducePointsMiddleware implements Middleware
{
    public function __construct(
        private PointReducer $reducer = new PointReducer(),
    ) {
    }

    public function handle(Document $document, callable $next): Document
    {
        return $next($document->withTracks(array_map(
            fn (Track $track): Track => $track->withSegments(array_map(
                fn (TrackSegment $segment): TrackSegment => $segment->withPoints($this->reducer->reduce($segment->points)),
                $track->segments,
            )),
            $document->tracks,
        )));
    }
}
