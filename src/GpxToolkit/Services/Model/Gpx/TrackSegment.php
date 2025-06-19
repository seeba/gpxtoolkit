<?php

namespace GpxToolkit\Services\Model\Gpx;

use GpxToolkit\Services\Parser\GpxParser;
use SimpleXMLElement;

readonly class TrackSegment extends Segment implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'trkseg';

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }

        return new self(
            GpxParser::parseCollection($element->trkpt, TrackPoint::class),
            null
        );
    }
}
