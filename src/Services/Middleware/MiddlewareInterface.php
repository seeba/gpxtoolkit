<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Middleware;

interface MiddlewareInterface
{
    public function handle(mixed $data, callable $next): mixed;
}
