<?php

declare(strict_types=1);

namespace GpxToolkit\Tests;

use DateTimeImmutable;
use GpxToolkit\GPXToolkit;
use GpxToolkit\Services\File\FileInterface;
use GpxToolkit\Services\Middleware\CreateBoundsIfNotExistMiddleware;
use GpxToolkit\Services\Middleware\CreateWktMiddleware;
use GpxToolkit\Services\Middleware\MiddlewareInterface;
use GpxToolkit\Services\Middleware\PrepareStatisticsMiddleware;
use GpxToolkit\Services\Middleware\ReducePointsMiddleware;
use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\Point;
use GpxToolkit\Services\Model\Gpx\Track;
use GpxToolkit\Services\Model\Gpx\TrackPoint;
use GpxToolkit\Services\Model\Gpx\TrackSegment;
use GpxToolkit\Services\Utils\StatisticCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GPXToolkitTest extends TestCase
{
    #[Test]
    public function it_parses_valid_gpx_without_optional_track_name_and_elevation(): void
    {
        GPXToolkit::configure();

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0">
                            <time>2026-05-13T10:00:00Z</time>
                        </trkpt>
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertInstanceOf(Gpx::class, $gpx);
        self::assertNull($gpx->tracks[0]->name);
        self::assertInstanceOf(TrackSegment::class, $gpx->tracks[0]->segments[0]);
        self::assertInstanceOf(TrackPoint::class, $gpx->tracks[0]->segments[0]->points[0]);
        self::assertNull($gpx->tracks[0]->segments[0]->points[0]->elevation);
    }

    #[Test]
    public function it_runs_before_middleware_before_parsing_and_resets_configured_middleware(): void
    {
        $beforeCalls = 0;
        $afterCalls = 0;

        GPXToolkit::configure(
            beforeMiddlewares: [
                new class($beforeCalls) implements MiddlewareInterface {
                    public function __construct(private int &$calls)
                    {
                    }

                    public function handle(mixed $data, callable $next): mixed
                    {
                        ++$this->calls;
                        TestCase::assertInstanceOf(FileInterface::class, $data);

                        return $next($data);
                    }
                },
            ],
            afterMiddlewares: [
                new class($afterCalls) implements MiddlewareInterface {
                    public function __construct(private int &$calls)
                    {
                    }

                    public function handle(mixed $data, callable $next): mixed
                    {
                        ++$this->calls;
                        TestCase::assertInstanceOf(Gpx::class, $data);

                        return $next($data);
                    }
                },
            ],
        );

        GPXToolkit::parse($this->createTempGpxFile($this->minimalGpx()));

        GPXToolkit::configure();
        GPXToolkit::parse($this->createTempGpxFile($this->minimalGpx()));

        self::assertSame(1, $beforeCalls);
        self::assertSame(1, $afterCalls);
    }

    #[Test]
    public function it_throws_domain_exception_for_invalid_xml(): void
    {
        GPXToolkit::configure();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid GPX XML');

        GPXToolkit::parse($this->createTempGpxFile('<gpx><trk>'));
    }

    #[Test]
    public function it_generates_track_wkt_from_only_that_tracks_points(): void
    {
        GPXToolkit::configure(afterMiddlewares: [new CreateWktMiddleware()]);

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <name>first</name>
                    <trkseg>
                        <trkpt lat="10.0" lon="20.0" />
                        <trkpt lat="11.0" lon="21.0" />
                    </trkseg>
                </trk>
                <trk>
                    <name>second</name>
                    <trkseg>
                        <trkpt lat="30.0" lon="40.0" />
                        <trkpt lat="31.0" lon="41.0" />
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertSame('LINESTRING(20 10, 21 11)', $gpx->tracks[0]->wktLineString);
        self::assertSame('LINESTRING(40 30, 41 31)', $gpx->tracks[1]->wktLineString);
    }

    #[Test]
    public function it_does_not_reuse_speed_distribution_between_calculations(): void
    {
        $gpx = Gpx::create(
            creator: 'test',
            version: '1.1',
            tracks: [
                Track::create(
                    segments: [
                        TrackSegment::create([
                            Point::create(50.0, 19.0, 100.0, new DateTimeImmutable('2026-05-13T10:00:00Z')),
                            Point::create(50.001, 19.0, 101.0, new DateTimeImmutable('2026-05-13T10:00:10Z')),
                        ]),
                    ],
                ),
            ],
        );

        $first = StatisticCalculator::calculateGpxStatistics($gpx);
        $second = StatisticCalculator::calculateGpxStatistics($gpx);

        self::assertSame($first['speedDistribution'], $second['speedDistribution']);
        self::assertSame(1, array_sum($second['speedDistribution']));
    }

    #[Test]
    public function it_creates_bounds_when_metadata_does_not_have_them(): void
    {
        GPXToolkit::configure(afterMiddlewares: [new CreateBoundsIfNotExistMiddleware()]);

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                        <trkpt lat="51.5" lon="18.5" />
                        <trkpt lat="49.5" lon="20.0" />
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertNotNull($gpx->metadata);
        self::assertNotNull($gpx->metadata->bounds);
        self::assertSame(49.5, $gpx->metadata->bounds->minLatitude);
        self::assertSame(18.5, $gpx->metadata->bounds->minLongitude);
        self::assertSame(51.5, $gpx->metadata->bounds->maxLatitude);
        self::assertSame(20.0, $gpx->metadata->bounds->maxLongitude);
    }

    #[Test]
    public function it_keeps_existing_bounds(): void
    {
        GPXToolkit::configure(afterMiddlewares: [new CreateBoundsIfNotExistMiddleware()]);

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <metadata>
                    <bounds minlat="1.0" minlon="2.0" maxlat="3.0" maxlon="4.0" />
                </metadata>
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertSame(1.0, $gpx->metadata->bounds->minLatitude);
        self::assertSame(2.0, $gpx->metadata->bounds->minLongitude);
        self::assertSame(3.0, $gpx->metadata->bounds->maxLatitude);
        self::assertSame(4.0, $gpx->metadata->bounds->maxLongitude);
    }

    #[Test]
    public function it_prepares_statistics_for_points_without_timestamps_or_elevation(): void
    {
        GPXToolkit::configure(afterMiddlewares: [new PrepareStatisticsMiddleware()]);

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                        <trkpt lat="50.001" lon="19.0" />
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertNotNull($gpx->statistics);
        self::assertGreaterThan(0, $gpx->statistics->totalDistance);
        self::assertSame(0, $gpx->statistics->totalDuration);
        self::assertSame(0.0, $gpx->statistics->averageSpeed);
        self::assertSame(2, $gpx->statistics->totalPoints);
        self::assertSame(0, array_sum($gpx->statistics->speedDistribution));
    }

    #[Test]
    public function it_reduces_straight_track_to_first_and_last_point(): void
    {
        GPXToolkit::configure(afterMiddlewares: [new ReducePointsMiddleware()]);

        $gpx = GPXToolkit::parse($this->createTempGpxFile(<<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                        <trkpt lat="50.000005" lon="19.000005" />
                        <trkpt lat="50.000010" lon="19.000010" />
                    </trkseg>
                </trk>
            </gpx>
            GPX));

        self::assertCount(2, $gpx->tracks[0]->segments[0]->points);
        self::assertInstanceOf(TrackSegment::class, $gpx->tracks[0]->segments[0]);
        self::assertSame(50.0, $gpx->tracks[0]->segments[0]->points[0]->latitude);
        self::assertSame(50.000010, $gpx->tracks[0]->segments[0]->points[1]->latitude);
    }

    #[Test]
    public function it_rejects_unsupported_file_extensions(): void
    {
        GPXToolkit::configure();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file type: txt');

        GPXToolkit::parse($this->createTempFile('txt', $this->minimalGpx()));
    }

    private function createTempGpxFile(string $content): string
    {
        return $this->createTempFile('gpx', $content);
    }

    private function createTempFile(string $extension, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gpxtoolkit-').'.'.$extension;
        file_put_contents($path, $content);

        return $path;
    }

    private function minimalGpx(): string
    {
        return <<<'GPX'
            <?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                    </trkseg>
                </trk>
            </gpx>
            GPX;
    }
}
