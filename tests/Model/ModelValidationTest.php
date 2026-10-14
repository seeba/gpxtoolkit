<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Model;

use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Model\Bounds;
use GpxToolkit\Model\Point;
use GpxToolkit\Model\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModelValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidModels(): iterable
    {
        yield 'latitude' => [static fn () => new Point(-90.1, 0.0), 'Latitude must be between -90 and 90'];
        yield 'longitude' => [static fn () => new Point(0.0, 181.0), 'Longitude must be between -180 and 180'];
        yield 'infinite latitude' => [static fn () => new Point(INF, 0.0), 'Latitude must be a finite number'];
        yield 'NaN elevation' => [static fn () => new Point(0.0, 0.0, NAN), 'Elevation must be a finite number'];
        yield 'negative satellites' => [static fn () => new Point(0.0, 0.0, satelliteCount: -1), 'Satellite count must not be negative'];
        yield 'DGPS id' => [static fn () => new Point(0.0, 0.0, dgpsId: 1024), 'DGPS id must be between 0 and 1023'];
        yield 'bounds latitude' => [static fn () => new Bounds(-91.0, 0.0, 0.0, 0.0), 'Minimum latitude must be between -90 and 90'];
        yield 'bounds order' => [static fn () => new Bounds(0.0, 10.0, 1.0, 5.0), 'Bounds minimum must not exceed maximum'];
        yield 'route number' => [static fn () => new Route(number: -1), 'Number must not be negative'];
    }

    #[Test]
    #[DataProvider('invalidModels')]
    public function it_validates_model_invariants(callable $create, string $message): void
    {
        try {
            $create();
            self::fail('Expected the model to be rejected.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertNull($exception->source);
            self::assertNull($exception->elementPath);
        }
    }
}
