<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

class Email implements GpxElementInterface
{
    private const string ELEMENT_NAME = 'email';

    private function __construct(
        public string $id,
        public string $domain
    ) {
    }

    public static function parse(SimpleXMLElement $element): ?self
    {
        if ($element->getName() != self::ELEMENT_NAME) {
            return null;
        }
        return new self(
            isset($element['id']) ? (string)$element['id'] : 'undefined',
            isset($element['domain']) ? (string)$element['domain'] : 'undefined'
        );
    }
}
