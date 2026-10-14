<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * A parsed GPX 1.1 document. Contains source data only; derived results
 * (statistics, bounds, geometry) are computed by the Analysis and Geometry services.
 */
final readonly class Document
{
    /**
     * @param list<Point> $waypoints
     * @param list<Route> $routes
     * @param list<Track> $tracks
     */
    public function __construct(
        public string $version,
        public string $creator,
        public ?Metadata $metadata = null,
        public array $waypoints = [],
        public array $routes = [],
        public array $tracks = [],
        public ?Extensions $extensions = null,
    ) {
    }

    /**
     * @param list<Track> $tracks
     */
    public function withTracks(array $tracks): self
    {
        return new self(
            $this->version,
            $this->creator,
            $this->metadata,
            $this->waypoints,
            $this->routes,
            $tracks,
            $this->extensions,
        );
    }

    /**
     * @param list<Route> $routes
     */
    public function withRoutes(array $routes): self
    {
        return new self(
            $this->version,
            $this->creator,
            $this->metadata,
            $this->waypoints,
            $routes,
            $this->tracks,
            $this->extensions,
        );
    }
}
