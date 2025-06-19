<?php

declare(strict_types=1);

namespace GpxToolkit\Services\File;

use GpxToolkit\Services\Parser\ParserInterface;

class FileHandlerRegistry
{
    /**
     * @var FileInterface[]
     */
    private static array $fileHandlers = [];
    /**
     * @var ParserInterface[]
     */
    private static array $parsers = [];

    public static function registerHandler(string $extension, string $fileHandler, string $parser): void
    {
        if (!in_array(FileInterface::class, class_implements($fileHandler))) {
            throw new \InvalidArgumentException("Handler class must implement FileInterface");
        }

        if (!in_array(ParserInterface::class, class_implements($parser))) {
            throw new \InvalidArgumentException("Parser class must implement ParserInterface");
        }

        self::$fileHandlers[strtolower($extension)] = $fileHandler;
        self::$parsers[strtolower($extension)] = $parser;
    }

    public static function createFileHandler(string $path): FileInterface
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!isset(self::$fileHandlers[$extension])) {
            throw new \InvalidArgumentException("Unsupported file type: " . $extension);
        }

        $handlerClass = self::$fileHandlers[$extension];
        return $handlerClass::create($path);
    }

    public static function getParser(string $extension): ParserInterface
    {
        $extension = strtolower($extension);
        if (!isset(self::$parsers[$extension])) {
            throw new \InvalidArgumentException("No parser registered for file type: " . $extension);
        }

        $parserClass = self::$parsers[$extension];
        return new $parserClass();
    }
}
