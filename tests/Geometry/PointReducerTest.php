<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Geometry;

use GpxToolkit\Geometry\PointReducer;
use GpxToolkit\Model\Point;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointReducerTest extends TestCase
{
    // One meter of latitude in degrees.
    private const float METER = 1 / 111_194.93;

    #[Test]
    public function it_keeps_short_lines_unchanged(): void
    {
        $points = [new Point(50.0, 19.0), new Point(50.0, 19.0)];

        self::assertSame($points, (new PointReducer())->reduce($points));
        self::assertSame([], (new PointReducer())->reduce([]));
    }

    #[Test]
    public function it_measures_tolerance_in_meters(): void
    {
        $points = [
            new Point(50.0, 19.0),
            new Point(50.0 + 3 * self::METER, 19.001),
            new Point(50.0, 19.002),
        ];

        self::assertCount(3, (new PointReducer(toleranceMeters: 2.0))->reduce($points));
        self::assertCount(2, (new PointReducer(toleranceMeters: 4.0))->reduce($points));
    }

    #[Test]
    public function it_scales_longitude_by_latitude(): void
    {
        // 0.00004° of longitude is about 2.9 m at the equator and 1.4 m at 70°N.
        $line = static fn (float $latitude): array => [
            new Point($latitude, 19.0),
            new Point($latitude + 0.001, 19.00004),
            new Point($latitude + 0.002, 19.0),
        ];

        $reducer = new PointReducer(toleranceMeters: 2.0);
        self::assertCount(3, $reducer->reduce($line(0.0)));
        self::assertCount(2, $reducer->reduce($line(70.0)));
    }

    #[Test]
    public function it_keeps_shape_of_closed_loop(): void
    {
        $points = [
            new Point(50.000, 19.000),
            new Point(50.000, 19.010),
            new Point(50.010, 19.010),
            new Point(50.010, 19.000),
            new Point(50.000, 19.000),
        ];

        self::assertSame($points, (new PointReducer())->reduce($points));
    }

    #[Test]
    public function it_keeps_turning_point_of_out_and_back_path(): void
    {
        $points = [
            new Point(50.000, 19.0),
            new Point(50.005, 19.0),
            new Point(50.010, 19.0),
            new Point(50.005, 19.0),
            new Point(50.001, 19.0),
        ];

        $reduced = (new PointReducer())->reduce($points);

        self::assertSame([$points[0], $points[2], $points[4]], $reduced);
    }

    #[Test]
    public function it_collapses_identical_points(): void
    {
        $point = new Point(50.0, 19.0);
        $points = [$point, new Point(50.0, 19.0), new Point(50.0, 19.0), new Point(50.0, 19.0)];

        self::assertSame([$point, $points[3]], (new PointReducer())->reduce($points));
    }

    #[Test]
    public function it_drops_only_collinear_points_with_zero_tolerance(): void
    {
        $points = [
            new Point(50.0, 19.0),
            new Point(50.0, 19.0001),
            new Point(50.0, 19.0002),
            new Point(50.0 + self::METER / 100, 19.0003),
        ];

        self::assertSame([$points[0], $points[2], $points[3]], (new PointReducer(toleranceMeters: 0.0))->reduce($points));
    }

    #[Test]
    public function it_handles_lines_crossing_the_antimeridian(): void
    {
        $points = [
            new Point(10.0, 179.999),
            new Point(10.0, 180.0),
            new Point(10.0, -179.999),
        ];

        self::assertSame([$points[0], $points[2]], (new PointReducer())->reduce($points));
    }

    #[Test]
    public function it_rejects_invalid_tolerance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PointReducer(toleranceMeters: -1.0);
    }
}
