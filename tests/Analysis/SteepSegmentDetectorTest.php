<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use GpxToolkit\Analysis\SteepSegmentDetector;
use GpxToolkit\Analysis\SteepSegmentType;
use GpxToolkit\Model\Point;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SteepSegmentDetectorTest extends TestCase
{
    // About 111 m of latitude.
    private const float STEP = 0.001;

    #[Test]
    public function it_detects_climbs_and_descents(): void
    {
        $points = self::profile([100.0, 100.0, 120.0, 140.0, 140.0, 115.0, 115.0]);

        $segments = (new SteepSegmentDetector())->detect($points);

        self::assertCount(2, $segments);
        self::assertSame(SteepSegmentType::Climb, $segments[0]->type);
        self::assertSame(1, $segments[0]->startPointIndex);
        self::assertSame(3, $segments[0]->endPointIndex);
        self::assertSame($points[1], $segments[0]->start);
        self::assertSame($points[3], $segments[0]->end);
        self::assertSame(40.0, $segments[0]->elevationChangeMeters);
        self::assertEqualsWithDelta(222.4, $segments[0]->distanceMeters, 0.1);
        self::assertEqualsWithDelta(17.99, $segments[0]->gradePercent, 0.01);

        self::assertSame(SteepSegmentType::Descent, $segments[1]->type);
        self::assertSame(4, $segments[1]->startPointIndex);
        self::assertSame(5, $segments[1]->endPointIndex);
        self::assertSame(-25.0, $segments[1]->elevationChangeMeters);
        self::assertLessThan(0, $segments[1]->gradePercent);
    }

    #[Test]
    public function it_splits_runs_when_direction_changes(): void
    {
        $segments = (new SteepSegmentDetector())->detect(self::profile([100.0, 120.0, 100.0]));

        self::assertSame(
            [SteepSegmentType::Climb, SteepSegmentType::Descent],
            array_map(static fn ($segment) => $segment->type, $segments),
        );
    }

    #[Test]
    public function it_breaks_runs_at_points_without_elevation(): void
    {
        $points = self::profile([100.0, 120.0, null, 160.0, 180.0]);

        $segments = (new SteepSegmentDetector(minDistanceMeters: 0.0))->detect($points);

        self::assertCount(2, $segments);
        self::assertSame([0, 1], [$segments[0]->startPointIndex, $segments[0]->endPointIndex]);
        self::assertSame([3, 4], [$segments[1]->startPointIndex, $segments[1]->endPointIndex]);
    }

    #[Test]
    public function it_skips_runs_shorter_than_minimum_distance(): void
    {
        $segments = (new SteepSegmentDetector(minDistanceMeters: 150.0))->detect(self::profile([100.0, 120.0, 120.0]));

        self::assertSame([], $segments);
    }

    #[Test]
    public function it_ignores_pairs_without_distance(): void
    {
        $points = [new Point(50.0, 19.0, 100.0), new Point(50.0, 19.0, 150.0)];

        self::assertSame([], (new SteepSegmentDetector(minDistanceMeters: 0.0))->detect($points));
    }

    #[Test]
    public function it_rejects_invalid_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SteepSegmentDetector(gradeThresholdPercent: 0.0);
    }

    /**
     * @param list<float|null> $elevations
     *
     * @return list<Point>
     */
    private static function profile(array $elevations): array
    {
        return array_map(
            static fn (?float $elevation, int $index): Point => new Point(50.0 + $index * self::STEP, 19.0, $elevation),
            $elevations,
            array_keys($elevations),
        );
    }
}
