<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use DateTimeImmutable;
use GpxToolkit\Services\Enum\FixType;
use GpxToolkit\Services\Parser\GpxParser;
use SimpleXMLElement;

class Point extends AbstractPoint implements GpxElementInterface
{
    private function __construct(
        float $latitude,
        float $longitude,
        ?float $elevation = null,
        ?DateTimeImmutable $time = null,
        public ?float $magVar = null,
        public ?float $geoidHeight = null,
        public ?string $name = null,
        public ?string $comment = null,
        public ?string $description = null,
        public ?string $source = null,
        /**
         * @var GpxElementInterface[]
         */
        public array $links = [],
        public ?string $symbol = null,
        public ?string $type = null,
        public ?FixType $fix = null,
        public ?int $satelitesQuantity = null,
        public ?float $hdop = null,
        public ?float $vdop = null,
        public ?float $pdop = null,
        public ?int $ageOfGpsData = null,
        public ?int $dgsid = null,
        public ?Extensions $extensions = null
    ) {
        parent::__construct($latitude, $longitude, $elevation, $time);
    }

    public static function parse(SimpleXMLElement $element): self
    {
        return new self(
            (float)$element['lat'],
            (float)$element['lon'],
            isset($element->ele) ? ((float)$element->ele) : null,
            isset($element->time) ? new DateTimeImmutable((string)$element->time) : null,
            isset($element->magvar) ? ((float)$element->magvar) : null,
            isset($element->geoidheight) ? ((float)$element->geoidheight) : null,
            isset($element->name) ? ((string)$element->name) : null,
            isset($element->cmt) ? ((string)$element->cmt) : null,
            isset($element->desc) ? ((string)$element->desc) : null,
            isset($element->src) ? ((string)$element->src) : null,
            GpxParser::parseCollection($element->link, Link::class),
            isset($element->sym) ? ((string)$element->sym) : null,
            isset($element->type) ? ((string)$element->type) : null,
            isset($element->fix) ? FixType::from((string)$element->fix) : null,
            isset($element->sat) ? ((int)$element->sat) : null,
            isset($element->hdop) ? ((float)$element->hdop) : null,
            isset($element->vdop) ? ((float)$element->vdop) : null,
            isset($element->pdop) ? ((float)$element->pdop) : null,
            isset($element->ageofgpsdata) ? ((int)$element->ageofgpsdata) : null,
            isset($element->dgpsid) ? ((int)$element->dgpsid) : null,
            null //Extensions
        );
    }
}
