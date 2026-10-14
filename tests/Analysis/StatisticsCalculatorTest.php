<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use DateTimeImmutable;
use GpxToolkit\Analysis\StatisticsCalculator;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Point;
use GpxToolkit\Model\Track;
use GpxToolkit\Model\TrackSegment;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatisticsCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_track_statistics(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('track.gpx'));

        $statistics = (new StatisticsCalculator())->calculate($document);

        self::assertEqualsWithDelta(111.19 + 132.19, $statistics->distanceMeters, 0.1);
        self::assertSame(5.0, $statistics->elevationGainMeters);
        self::assertSame(2.0, $statistics->elevationLossMeters);
        self::assertSame(120, $statistics->durationSeconds);
        self::assertEqualsWithDelta($statistics->distanceMeters / 120, $statistics->averageSpeedMetersPerSecond, 1e-9);
        self::assertEqualsWithDelta(1000 / $statistics->averageSpeedMetersPerSecond, $statistics->averagePaceSecondsPerKilometer, 1e-9);
        self::assertSame(3, $statistics->pointCount);
        self::assertSame(2, array_sum($statistics->speedDistributionKmh));
    }

    #[Test]
    public function it_handles_points_without_time(): void
    {
        $document = new Document('1.1', 'test', tracks: [
            new Track(segments: [new TrackSegment([new Point(50.0, 19.0), new Point(50.001, 19.0)])]),
        ]);

        $statistics = (new StatisticsCalculator())->calculate($document);

        self::assertGreaterThan(0, $statistics->distanceMeters);
        self::assertSame(0, $statistics->durationSeconds);
        self::assertSame(0.0, $statistics->averageSpeedMetersPerSecond);
        self::assertNull($statistics->averagePaceSecondsPerKilometer);
        self::assertSame(0, array_sum($statistics->speedDistributionKmh));
    }

    #[Test]
    public function it_does_not_share_state_between_calculations(): void
    {
        $document = new Document('1.1', 'test', tracks: [
            new Track(segments: [new TrackSegment([
                new Point(50.0, 19.0, 100.0, new DateTimeImmutable('2026-05-13T10:00:00Z')),
                new Point(50.001, 19.0, 101.0, new DateTimeImmutable('2026-05-13T10:00:10Z')),
            ])]),
        ]);
        $calculator = new StatisticsCalculator();

        self::assertEquals($calculator->calculate($document), $calculator->calculate($document));
    }
}
