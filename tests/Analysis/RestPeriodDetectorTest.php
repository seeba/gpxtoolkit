<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use DateTimeImmutable;
use GpxToolkit\Analysis\RestPeriodDetector;
use GpxToolkit\Model\Point;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RestPeriodDetectorTest extends TestCase
{
    #[Test]
    public function it_detects_slow_runs_longer_than_minimum(): void
    {
        $periods = (new RestPeriodDetector())->detect([
            self::point(50.000, '10:00:00'),
            self::point(50.001, '10:01:00'),
            self::point(50.001, '10:03:00'),
            self::point(50.001, '10:06:00'),
            self::point(50.002, '10:07:00'),
            self::point(50.002, '10:08:00'),
        ]);

        self::assertCount(1, $periods);
        self::assertEquals(new DateTimeImmutable('2026-05-13T10:01:00Z'), $periods[0]->start);
        self::assertEquals(new DateTimeImmutable('2026-05-13T10:06:00Z'), $periods[0]->end);
        self::assertSame(300.0, $periods[0]->durationSeconds);
        self::assertSame(0.0, $periods[0]->distanceMeters);
        self::assertSame(1, $periods[0]->startPointIndex);
        self::assertSame(3, $periods[0]->endPointIndex);
    }

    #[Test]
    public function it_breaks_runs_at_points_without_time(): void
    {
        $periods = (new RestPeriodDetector())->detect([
            self::point(50.0, '10:00:00'),
            self::point(50.0, '10:01:30'),
            new Point(50.0, 19.0),
            self::point(50.0, '10:03:00'),
            self::point(50.0, '10:04:30'),
        ]);

        self::assertSame([], $periods);
    }

    #[Test]
    public function it_detects_rest_at_line_end(): void
    {
        $periods = (new RestPeriodDetector(minDurationSeconds: 60))->detect([
            self::point(50.000, '10:00:00'),
            self::point(50.001, '10:01:00'),
            self::point(50.001, '10:02:00'),
        ]);

        self::assertCount(1, $periods);
        self::assertSame(2, $periods[0]->endPointIndex);
    }

    #[Test]
    public function it_rejects_invalid_parameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RestPeriodDetector(speedThresholdMetersPerSecond: 0.0);
    }

    private static function point(float $latitude, string $time): Point
    {
        return new Point($latitude, 19.0, time: new DateTimeImmutable('2026-05-13T'.$time.'Z'));
    }
}
