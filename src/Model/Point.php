<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

use DateTimeImmutable;

/**
 * GPX 1.1 wptType, used for waypoints (wpt), route points (rtept) and track points (trkpt).
 *
 * Coordinates are decimal degrees (WGS 84), elevation and geoid height are meters,
 * magnetic variation is degrees and the age of DGPS data is seconds.
 */
final readonly class Point
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $elevation = null,
        public ?DateTimeImmutable $time = null,
        public ?float $magneticVariation = null,
        public ?float $geoidHeight = null,
        public ?string $name = null,
        public ?string $comment = null,
        public ?string $description = null,
        public ?string $source = null,
        public array $links = [],
        public ?string $symbol = null,
        public ?string $type = null,
        public ?FixType $fix = null,
        public ?int $satelliteCount = null,
        public ?float $hdop = null,
        public ?float $vdop = null,
        public ?float $pdop = null,
        public ?float $ageOfDgpsData = null,
        public ?int $dgpsId = null,
        public ?Extensions $extensions = null,
    ) {
    }
}
