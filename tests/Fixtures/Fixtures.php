<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Fixtures;

final class Fixtures
{
    public static function path(string $name): string
    {
        return __DIR__.'/gpx/'.$name;
    }

    public static function content(string $name): string
    {
        return (string) file_get_contents(self::path($name));
    }
}
