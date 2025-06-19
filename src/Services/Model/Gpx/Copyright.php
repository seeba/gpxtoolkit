<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

readonly class Copyright implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'copyright';

    private function __construct(
        public string $author,
        public ?string $year = null,
        public ?string $license = null
    ) {
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }

        return new self(
            isset($element['author']) ? (string)$element['author'] : 'undefined',
            isset($element->year) ? (string)$element->year : null,
            isset($element->license) ? (string)$element->license : null
        );
    }
}
