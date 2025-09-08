<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use GpxToolkit\Services\Model\Additional\Statistics;
use GpxToolkit\Services\Model\WorkoutDataInterface;
use GpxToolkit\Services\Parser\GpxParser;
use SimpleXMLElement;

class Gpx implements WorkoutDataInterface, GpxElementInterface
{
    private function __construct(
        public readonly string $creator,
        public readonly string $version,
        public ?Metadata $metadata,
        /**
         * @var GpxElementInterface[]|null
         */
        public readonly ?array $waypoints = [],
        /**
         * @var GpxElementInterface[]|null
         */
        public readonly ?array $routes = [],
        /**
         * @var GpxElementInterface[]|null
         */
        public readonly ?array $tracks = [],
        public readonly ?Extensions $extensions = null,
        public ?Statistics $statistics = null
    ) {
    }

    public static function create(
        string $creator,
        string $version,
        ?Metadata $metadata = null,
        ?array $waypoints = [],
        ?array $routes = [],
        ?array $tracks = [],
        ?Extensions $extensions = null
    ): self {
        return new self(
            $creator,
            $version,
            $metadata,
            $waypoints,
            $routes,
            $tracks,
            $extensions
        );
    }

    public static function parse(SimpleXMLElement $element): self
    {
        return new self(
            isset($element['creator']) ? (string)$element['creator'] : 'undefined',
            isset($element['version']) ? (string)$element['version'] : 'undefined',
            isset($element->metadata) ? Metadata::parse($element->metadata) : null,
            GpxParser::parseCollection($element->wpt, WayPoint::class),
            GpxParser::parseCollection($element->rte, Route::class),
            GpxParser::parseCollection($element->trk, Track::class),
            null //Extensions
        );
    }

    public function setMetadata(Metadata $metadata): void
    {
        $this->metadata = $metadata;
    }
}
