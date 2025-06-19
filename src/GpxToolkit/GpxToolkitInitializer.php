<?php

declare(strict_types=1);

namespace GpxToolkit;

use GpxToolkit\Services\File\FileHandlerRegistry;
use GpxToolkit\Services\File\GpxFile;
use GpxToolkit\Services\Parser\GpxParser;
use GpxToolkit\Services\Middleware\MiddlewareInterface;

class GpxToolkitInitializer
{
    /**
     * @var MiddlewareInterface[]
     */
    private static array $beforeParsingMiddleware = [];
    /**
     * @var MiddlewareInterface[]
     */
    private static array $afterParsingMiddleware = [];

    public static function initialize(): void
    {
        FileHandlerRegistry::registerHandler('gpx', GpxFile::class, GpxParser::class);
    }

    public static function registerBeforeParsingMiddleware(MiddlewareInterface $middleware): void
    {
        self::$beforeParsingMiddleware[] = $middleware;
    }

    public static function registerAfterParsingMiddleware(MiddlewareInterface $middleware): void
    {
        self::$afterParsingMiddleware[] = $middleware;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public static function getBeforeParsingMiddleware(): array
    {
        return self::$beforeParsingMiddleware;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public static function getAfterParsingMiddleware(): array
    {
        return self::$afterParsingMiddleware;
    }
}
