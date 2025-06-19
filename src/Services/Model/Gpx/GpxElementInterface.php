<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use SimpleXMLElement;

interface GpxElementInterface
{
    public static function parse(SimpleXMLElement $element): ?GpxElementInterface;
}
