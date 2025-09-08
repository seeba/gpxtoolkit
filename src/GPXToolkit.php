<?php

declare(strict_types=1);

namespace GpxToolkit;

use GpxToolkit\Services\File\FileHandlerRegistry;
use GpxToolkit\Services\Model\WorkoutDataInterface;
use GpxToolkit\Services\Middleware\MiddlewareInterface;

class GPXToolkit
{
    /**
     * @param MiddlewareInterface[] $beforeMiddlewares
     * @param MiddlewareInterface[] $afterMiddlewares
     */
    public static function configure(array $beforeMiddlewares = [], array $afterMiddlewares = []): void
    {
        GpxToolkitInitializer::initialize();
        foreach ($afterMiddlewares as $middleware) {
            GpxToolkitInitializer::registerAfterParsingMiddleware($middleware);
        }

        foreach ($beforeMiddlewares as $middleware) {
            GpxToolkitInitializer::registerBeforeParsingMiddleware($middleware);
        }
    }
    /**
     * @param string $path
     * @return WorkoutDataInterface
     */
    public static function parse(
        string $path,

    ): WorkOutDataInterface {
        $file = FileHandlerRegistry::createFileHandler($path);
        $parser = FileHandlerRegistry::getParser($file->getExtension());
        return self::executeMiddleware($parser->parse($file), GpxToolkitInitializer::getAfterParsingMiddleware());
    }

    /**
     * @param MiddlewareInterface[] $middlewareQueue
     */
    private static function executeMiddleware(mixed $data, array $middlewareQueue): mixed
    {
        $next = function (mixed $data) use (&$middlewareQueue, &$next): mixed {
            if (empty($middlewareQueue)) {
                return $data;
            }
            $middleware = array_shift($middlewareQueue);

            return $middleware->handle($data, $next);
        };

        return $next($data);
    }
}
