<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

readonly class Bounds implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'bounds';

    private function __construct(
        public float $minLatitude,
        public float $minLongitude,
        public float $maxLatitude,
        public float $maxLongitude
    ) {
    }

    public static function create(
        float $minLatitude,
        float $minLongitude,
        float $maxLatitude,
        float $maxLongitude
    ): self {
        return new self($minLatitude, $minLongitude, $maxLatitude, $maxLongitude);
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }
        return new self(
            (float)$element['minlat'],
            (float)$element['minlon'],
            (float)$element['maxlat'],
            (float)$element['maxlon']
        );
    }
}
