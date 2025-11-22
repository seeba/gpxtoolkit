<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Middleware;

use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\Segment;
use GpxToolkit\Services\Model\Gpx\Track;
use GpxToolkit\Services\Model\Gpx\TrackSegment;
use GpxToolkit\Services\Utils\GenerateWkt;
use GpxToolkit\Services\Utils\ReduceGPXPoints;

class CreateWktMiddleware implements MiddlewareInterface
{
    /**
     * @param Gpx $data
     */
    public function handle(mixed $data, callable $next): mixed
    {
        $points = [];
        $updatedTracks = [];
        /** @var Track $track */
        foreach ($data->tracks as $track) {

            $updatedSegments = [];

            /** @var Segment $segment */
            foreach ($track->segments as $segment) {
                $points = array_merge($points, $segment->points);

                $pointsWithWkt = GenerateWkt::generatePointsWkt($segment->points);
                $updatedSegments[] = TrackSegment::create($pointsWithWkt, $segment->extensions);
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
                GenerateWkt::generateWktLineString($points)
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
