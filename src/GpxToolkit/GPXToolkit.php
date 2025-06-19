<?php

declare(strict_types=1);

namespace GpxToolkit;

use GpxToolkit\Services\File\FileHandlerRegistry;
use GpxToolkit\Services\Model\WorkoutDataInterface;
use GpxToolkit\Services\Middleware\MiddlewareInterface;

class GPXToolkit
{
    /**
     * @param string $path
     * @param MiddlewareInterface[] $beforeMiddlewares
     * @param MiddlewareInterface[] $afterMiddlewares
     * @return WorkoutDataInterface
     */
    public static function parse(
        string $path,
        array $beforeMiddlewares = [],
        array $afterMiddlewares = [],
    ): WorkOutDataInterface {
        GpxToolkitInitializer::initialize();
        foreach ($afterMiddlewares as $middleware) {
            GpxToolkitInitializer::registerAfterParsingMiddleware($middleware);
        }

        foreach ($beforeMiddlewares as $middleware) {
            GpxToolkitInitializer::registerBeforeParsingMiddleware($middleware);
        }

        $file = FileHandlerRegistry::createFileHandler($path);
        $parser = FileHandlerRegistry::getParser($file->getExtension());
        $result = $parser->parse($file);
        return self::executeMiddleware($result, GpxToolkitInitializer::getAfterParsingMiddleware());
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
