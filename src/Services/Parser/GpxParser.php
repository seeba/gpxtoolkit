<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Parser;

use GpxToolkit\Services\File\FileInterface;
use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\GpxElementInterface;
use GpxToolkit\Services\Model\WorkoutDataInterface;
use SimpleXMLElement;

class GpxParser implements ParserInterface
{
    public function parse(FileInterface $file): WorkoutDataInterface
    {
        return Gpx::parse(simplexml_load_string($file->getContent()));
    }
    /**
     * @template T of GpxElementInterface
     * @param class-string<T> $className
     * @param SimpleXMLElement|null $elements
     * @return T[]
     */
    public static function parseCollection(?SimpleXMLElement $elements, string $className): array
    {
        $parsedElements = [];
        if ($elements) {
            foreach ($elements as $element) {
                $parsedElements[] = $className::parse($element);
            }
        }
        return $parsedElements;
    }
}
