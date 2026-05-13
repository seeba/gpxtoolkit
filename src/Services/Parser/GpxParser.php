<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Parser;

use GpxToolkit\Services\File\FileInterface;
use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\GpxElementInterface;
use GpxToolkit\Services\Model\WorkoutDataInterface;
use InvalidArgumentException;
use SimpleXMLElement;

class GpxParser implements ParserInterface
{
    public function parse(FileInterface $file): WorkoutDataInterface
    {
        $previousLibXmlErrorHandling = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $element = simplexml_load_string($file->getContent());
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibXmlErrorHandling);

        if (!$element instanceof SimpleXMLElement) {
            $message = $errors[0]->message ?? 'unknown XML parser error';

            throw new InvalidArgumentException(sprintf('Invalid GPX XML in file: %s (%s)', $file->getPath(), trim($message)));
        }

        return Gpx::parse($element);
    }

    /**
     * @template T of GpxElementInterface
     *
     * @param class-string<T> $className
     *
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
