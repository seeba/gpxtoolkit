<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use GpxToolkit\Services\Parser\GpxParser;
use SimpleXMLElement;

readonly class Route extends PointsCollection implements GpxElementInterface
{
    private const ELEMENT_NAME = 'rte';

    /**
     * @param GpxElementInterface[] $links
     */
    private function __construct(
        ?string $name = null,
        ?string $comment = null,
        ?string $description = null,
        ?string $source = null,
        array $links = [],
        ?int $number = null,
        ?string $type = null,
        ?Extensions $extensions = null,
        /**
         * @var GpxElementInterface[]
         */
        public array $points = [],
    ) {
        parent::__construct(
            $name,
            $comment,
            $description,
            $source,
            $links,
            $number,
            $type,
            $extensions
        );
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }

        return new self(
            isset($element->name) ? ((string)$element->name) : null,
            isset($element->cmt) ? ((string)$element->cmt) : null,
            isset($element->desc) ? ((string)$element->desc) : null,
            isset($element->src) ? ((string)$element->src) : null,
            GpxParser::parseCollection($element->link, Link::class),
            isset($element->number) ? ((int)$element->number) : null,
            isset($element->type) ? ((string)$element->type) : null,
            null, //extensions
            GpxParser::parseCollection($element->rtept, RoutePoint::class),
        );
    }
}
