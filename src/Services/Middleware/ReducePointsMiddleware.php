<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Middleware;

use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\Track;
use GpxToolkit\Services\Model\Gpx\TrackSegment;
use GpxToolkit\Services\Utils\ReduceGPXPoints;

class ReducePointsMiddleware implements MiddlewareInterface
{
    /**
     * @param Gpx $data
     */
    public function handle(mixed $data, callable $next): mixed
    {
        $updatedTracks = [];
        foreach ($data->tracks as $track) {
            $updatedSegments = [];
            foreach ($track->segments as $segment) {
                $reducedPoints = ReduceGPXPoints::reducePoints($segment->points, 0.00001);
                $updatedSegments[] = TrackSegment::create($reducedPoints, $segment->extensions);
            }
            $updatedTracks[] = Track::create(
                $track->name,
                $track->comment,
                $track->description,
                $track->source,
                $track->links,
                $track->number,
                $track->type,
                $track->extensions,
                $updatedSegments,
            );
        }
        $updatedData = Gpx::create(
            $data->creator,
            $data->version,
            $data->metadata,
            $data->waypoints,
            $data->routes,
            $updatedTracks,
            $data->extensions
        );

        return $next($updatedData);
    }
}
