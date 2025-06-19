<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

readonly class Person implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'author';

    private function __construct(
        public ?string $name = null,
        public ?Email $email = null,
        public ?Link $link = null
    ) {
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }

        return new self(
            isset($element->name) ? ((string)$element->name) : null,
            isset($element->email) ? Email::parse($element->email) : null,
            isset($element->link) ? Link::parse($element->link) : null
        );
    }
}
