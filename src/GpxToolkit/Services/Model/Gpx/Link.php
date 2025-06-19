<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

readonly class Link implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'link';

    private function __construct(
        public string $href,
        public ?string $text = null,
        public ?string $type = null
    ) {
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }

        return new self(
            isset($element['href']) ? (string)$element['href'] : 'undefined',
            isset($element->text) ? (string)$element->text : null,
            isset($element->type) ? (string)$element->type : null
        );
    }
}
