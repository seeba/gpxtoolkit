<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use GpxToolkit\Analysis\ElevationProfileBuilder;
use GpxToolkit\Analysis\ProfilePoint;
use GpxToolkit\Geometry\Scope;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ElevationProfileBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_cumulative_profile_keeping_segment_boundaries(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('multi-track-multi-segment.gpx'));

        $profile = (new ElevationProfileBuilder())->build($document);

        self::assertCount(5, $profile);
        self::assertEqualsWithDelta(
            [0.0, 1111.95, 1111.95, 2223.9, 2223.9],
            array_map(static fn (ProfilePoint $point): float => $point->distanceMeters, $profile),
            0.01,
        );
        self::assertSame(
            [200.0, 220.0, 240.0, 230.0, 230.0],
            array_map(static fn (ProfilePoint $point): ?float => $point->elevationMeters, $profile),
        );
        self::assertSame(
            [[0, 0, 0], [0, 0, 1], [0, 1, 0], [0, 1, 1], [1, 0, 0]],
            array_map(static fn (ProfilePoint $point): array => [$point->pathIndex, $point->segmentIndex, $point->pointIndex], $profile),
        );
    }

    #[Test]
    public function it_keeps_points_without_elevation(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('missing-time-elevation.gpx'));

        $profile = (new ElevationProfileBuilder())->build($document);

        self::assertSame(
            [0.0, null, 10.0, null],
            array_map(static fn (ProfilePoint $point): ?float => $point->elevationMeters, $profile),
        );
    }

    #[Test]
    public function it_builds_route_profile(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('route.gpx'));

        $profile = (new ElevationProfileBuilder())->build($document, Scope::Routes);

        self::assertCount(3, $profile);
        self::assertSame([], (new ElevationProfileBuilder())->build($document));
    }
}
