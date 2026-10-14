<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Analysis;

use DateTimeImmutable;
use GpxToolkit\Analysis\RestPeriodDetector;
use GpxToolkit\Analysis\StatisticsCalculator;
use GpxToolkit\Geometry\Scope;
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
    /** Meters per 0.01 degree of latitude on the 6 371 000 m sphere. */
    private const float HUNDREDTH_DEGREE = 1111.9492664;

    #[Test]
    public function it_calculates_track_statistics(): void
    {
        $statistics = (new StatisticsCalculator())->calculate(self::parse('track.gpx'));

        self::assertSame(Scope::Tracks, $statistics->scope);
        self::assertSame(1, $statistics->lineCount);
        self::assertSame(3, $statistics->pointCount);
        self::assertSame(2, $statistics->pairCount);
        self::assertEqualsWithDelta(111.195 + 132.193, $statistics->distanceMeters, 0.01);
        self::assertSame(5.0, $statistics->elevationGainMeters);
        self::assertSame(2.0, $statistics->elevationLossMeters);
        self::assertSame(1.0, $statistics->elevationCompleteness());
        self::assertSame(200.0, $statistics->minElevationMeters);
        self::assertSame(205.0, $statistics->maxElevationMeters);
        self::assertEquals(new DateTimeImmutable('2026-05-13T10:00:00Z'), $statistics->startTime);
        self::assertEquals(new DateTimeImmutable('2026-05-13T10:02:00Z'), $statistics->endTime);
        self::assertSame(120.0, $statistics->durationSeconds);
        self::assertSame(120.0, $statistics->movingDurationSeconds);
        self::assertSame(0.0, $statistics->restDurationSeconds);
        self::assertSame(2, $statistics->timedPairCount);
        self::assertEqualsWithDelta($statistics->distanceMeters / 120, $statistics->averageSpeedMetersPerSecond, 1e-9);
        self::assertEqualsWithDelta($statistics->distanceMeters / 120, $statistics->movingSpeedMetersPerSecond, 1e-9);
        self::assertEqualsWithDelta(120_000 / $statistics->distanceMeters, $statistics->averagePaceSecondsPerKilometer(), 1e-9);
    }

    #[Test]
    public function it_does_not_bridge_gaps_between_segments_and_tracks(): void
    {
        $statistics = (new StatisticsCalculator())->calculate(self::parse('multi-track-multi-segment.gpx'));

        self::assertSame(4, $statistics->lineCount);
        self::assertSame(5, $statistics->pointCount);
        self::assertSame(2, $statistics->pairCount);
        self::assertEqualsWithDelta(2 * self::HUNDREDTH_DEGREE, $statistics->distanceMeters, 0.01);
        self::assertSame(20.0, $statistics->elevationGainMeters, 'Climb across the gap between segments must not count.');
        self::assertSame(10.0, $statistics->elevationLossMeters);
        self::assertSame(86_400.0, $statistics->durationSeconds);
        self::assertSame(1_800.0, $statistics->movingDurationSeconds);
        self::assertEqualsWithDelta($statistics->distanceMeters / 86_400, $statistics->averageSpeedMetersPerSecond, 1e-9);
        self::assertEqualsWithDelta($statistics->distanceMeters / 1_800, $statistics->movingSpeedMetersPerSecond, 1e-9);
    }

    #[Test]
    public function it_treats_missing_measurements_as_unknown(): void
    {
        $statistics = (new StatisticsCalculator())->calculate(self::parse('missing-time-elevation.gpx'));

        self::assertSame(3, $statistics->pairCount);
        self::assertSame(0, $statistics->elevationPairCount);
        self::assertNull($statistics->elevationGainMeters);
        self::assertNull($statistics->elevationLossMeters);
        self::assertSame(0.0, $statistics->elevationCompleteness());
        self::assertSame(0.0, $statistics->minElevationMeters, 'Zero is a real measurement.');
        self::assertSame(10.0, $statistics->maxElevationMeters);
        self::assertSame(180.0, $statistics->durationSeconds);
        self::assertSame(0, $statistics->timedPairCount);
        self::assertNull($statistics->movingDurationSeconds);
        self::assertNull($statistics->restDurationSeconds);
        self::assertNull($statistics->movingSpeedMetersPerSecond);
        self::assertNull($statistics->movingPaceSecondsPerKilometer());
        self::assertGreaterThan(0, $statistics->averageSpeedMetersPerSecond);
    }

    #[Test]
    public function it_keeps_point_order_and_skips_non_increasing_times(): void
    {
        $document = self::track([
            self::point(50.000, '10:00:00'),
            self::point(50.001, '10:01:00'),
            self::point(50.002, '10:00:30'),
            self::point(50.003, '10:00:30'),
            self::point(50.004, '10:02:00'),
        ]);

        $statistics = (new StatisticsCalculator())->calculate($document);

        self::assertEqualsWithDelta(4 * self::HUNDREDTH_DEGREE / 10, $statistics->distanceMeters, 0.01, 'Points must not be reordered by time.');
        self::assertSame(2, $statistics->nonIncreasingTimePairCount);
        self::assertSame(2, $statistics->timedPairCount);
        self::assertSame(150.0, $statistics->movingDurationSeconds);
        self::assertSame(120.0, $statistics->durationSeconds);
    }

    #[Test]
    public function it_excludes_rest_periods_from_moving_time(): void
    {
        $document = self::track([
            self::point(50.000, '10:00:00'),
            self::point(50.001, '10:01:00'),
            self::point(50.001, '10:03:00'),
            self::point(50.001, '10:06:00'),
            self::point(50.002, '10:07:00'),
        ]);

        $statistics = (new StatisticsCalculator())->calculate($document);

        self::assertSame(420.0, $statistics->durationSeconds);
        self::assertSame(300.0, $statistics->restDurationSeconds);
        self::assertSame(120.0, $statistics->movingDurationSeconds);
        self::assertEqualsWithDelta(2 * self::HUNDREDTH_DEGREE / 10 / 120, $statistics->movingSpeedMetersPerSecond, 1e-6);

        $withoutRest = (new StatisticsCalculator(new RestPeriodDetector(minDurationSeconds: 600)))->calculate($document);
        self::assertSame(420.0, $withoutRest->movingDurationSeconds);
    }

    #[Test]
    public function it_calculates_routes_separately_from_tracks(): void
    {
        $calculator = new StatisticsCalculator();
        $document = self::parse('track-and-route.gpx');

        $tracks = $calculator->calculate($document);
        $routes = $calculator->calculate($document, Scope::Routes);

        self::assertEqualsWithDelta(self::HUNDREDTH_DEGREE, $tracks->distanceMeters, 0.01);
        self::assertEqualsWithDelta(self::HUNDREDTH_DEGREE, $routes->distanceMeters, 0.01);
        self::assertSame(Scope::Routes, $routes->scope);
        self::assertSame(600.0, $tracks->durationSeconds);
        self::assertNull($routes->durationSeconds);
        self::assertNull($routes->averageSpeedMetersPerSecond);
    }

    #[Test]
    public function it_calculates_single_route_and_track(): void
    {
        $calculator = new StatisticsCalculator();
        $route = self::parse('route.gpx')->routes[0];
        $track = self::parse('multi-track-multi-segment.gpx')->tracks[0];

        $routeStatistics = $calculator->calculateRoute($route);
        self::assertEqualsWithDelta(self::HUNDREDTH_DEGREE + 714.598, $routeStatistics->distanceMeters, 0.01);
        self::assertSame(10.0, $routeStatistics->elevationGainMeters);
        self::assertSame(5.0, $routeStatistics->elevationLossMeters);

        $trackStatistics = $calculator->calculateTrack($track);
        self::assertSame(3, $trackStatistics->lineCount);
        self::assertSame(4_500.0, $trackStatistics->durationSeconds);
    }

    #[Test]
    public function it_returns_empty_statistics_without_points(): void
    {
        $statistics = (new StatisticsCalculator())->calculate(self::parse('waypoints-only.gpx'));

        self::assertSame(0, $statistics->lineCount);
        self::assertSame(0.0, $statistics->distanceMeters);
        self::assertNull($statistics->elevationCompleteness());
        self::assertNull($statistics->durationSeconds);
        self::assertNull($statistics->averagePaceSecondsPerKilometer());
    }

    private static function parse(string $fixture): Document
    {
        return (new Toolkit())->parseFile(Fixtures::path($fixture));
    }

    /**
     * @param list<Point> $points
     */
    private static function track(array $points): Document
    {
        return new Document('1.1', 'test', tracks: [new Track(segments: [new TrackSegment($points)])]);
    }

    private static function point(float $latitude, string $time): Point
    {
        return new Point($latitude, 19.0, time: new DateTimeImmutable('2026-05-13T'.$time.'Z'));
    }
}
