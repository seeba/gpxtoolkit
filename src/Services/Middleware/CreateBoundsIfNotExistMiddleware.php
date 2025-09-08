<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Middleware;

use GpxToolkit\Services\Model\Gpx\Bounds;
use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\Metadata;

class CreateBoundsIfNotExistMiddleware implements MiddlewareInterface
{
     /**
     * @param Gpx $data
     */
    public function handle(mixed $data, callable $next): mixed
    {
        if ($data?->metadata?->bounds === null) {
            $minLat = INF;
            $maxLat = -INF;
            $minLon = INF;
            $maxLon = -INF;

            foreach ($data->tracks as $track) {
                foreach ($track->segments as $segment) {
                    foreach ($segment->points as $point) {
                        $minLat = min($minLat, $point->latitude);
                        $maxLat = max($maxLat, $point->latitude);
                        $minLon = min($minLon, $point->longitude);
                        $maxLon = max($maxLon, $point->longitude);
                    }
                }
            }

            if (!isset($data->metadata)) {
                $data->setMetadata(Metadata::create(
                    bounds: Bounds::create($minLat, $minLon, $maxLat, $maxLon)
                ));
            } else {
                $data->metadata->setBounds(
                    Bounds::create($minLat, $minLon, $maxLat, $maxLon)
                );
            }
        }

        return $next($data);
    }
}
