<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use DateTime;
use GpxToolkit\Services\Parser\GpxParser;
use SimpleXMLElement;

class Metadata implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'metadata';

    private function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?Person $author = null,
        public ?Copyright $copyright = null,
        /**
         * @var GpxElementInterface[]
         */
        public array $links = [],
        public ?DateTime $time = null,
        public ?string $keywords = null,
        public ?Bounds $bounds = null
    ) {
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }
        return new self(
            isset($element->name) ? ((string)$element->name) : null,
            isset($element->description) ? ((string)$element->description) : null,
            isset($element->author) ? Person::parse($element->author) : null,
            isset($element->copyright) ? Copyright::parse($element->copyright) : null,
            GpxParser::parseCollection($element->link, Link::class),
            isset($element->time) ? new DateTime((string)$element->time) : null,
            isset($element->keywords) ? ((string)$element->keywords) : null,
            isset($element->bounds) ? Bounds::parse($element->bounds) : null
        );
    }

    public function setBounds(Bounds $bounds): void
    {
        $this->bounds = $bounds;
    }
}
