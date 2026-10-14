<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Geometry;

use GpxToolkit\Geometry\Scope;
use GpxToolkit\Geometry\WktWriter;
use GpxToolkit\Model\Point;
use GpxToolkit\Model\Track;
use GpxToolkit\Model\TrackSegment;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WktWriterTest extends TestCase
{
    #[Test]
    public function it_writes_longitude_before_latitude_with_fixed_precision(): void
    {
        $writer = new WktWriter();

        self::assertSame('POINT(19.5 50.25)', $writer->point(new Point(50.25, 19.5)));
        self::assertSame('POINT(19.1234568 -0.0000001)', $writer->point(new Point(-0.00000012, 19.123456789)));
        self::assertSame('POINT(0 0)', $writer->point(new Point(-0.00000001, 0.0)));
        self::assertSame('POINT(19.12 50)', (new WktWriter(2))->point(new Point(50.0, 19.123)));
    }

    #[Test]
    public function it_does_not_depend_on_locale(): void
    {
        $previous = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'pl_PL.UTF-8');

        try {
            self::assertSame('POINT(19.5 50.25)', (new WktWriter())->point(new Point(50.25, 19.5)));
        } finally {
            setlocale(LC_NUMERIC, false === $previous ? 'C' : $previous);
        }
    }

    #[Test]
    public function it_writes_line_and_skips_repeated_points(): void
    {
        $writer = new WktWriter();

        self::assertSame(
            'LINESTRING(20 10, 21 11)',
            $writer->lineString([new Point(10.0, 20.0), new Point(10.0, 20.0), new Point(11.0, 21.0)]),
        );
        self::assertNull($writer->lineString([new Point(10.0, 20.0)]));
        self::assertNull($writer->lineString([new Point(10.0, 20.0), new Point(10.00000001, 20.0)]));
        self::assertNull($writer->lineString([]));
    }

    #[Test]
    public function it_keeps_track_segments_apart(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('multi-track-multi-segment.gpx'));
        $writer = new WktWriter();

        self::assertSame('MULTILINESTRING((19 50, 19 50.01), (19 50.02, 19 50.03))', $writer->track($document->tracks[0]));
        self::assertNull($writer->track($document->tracks[1]), 'A single-point segment has no linear geometry.');
        self::assertSame('MULTILINESTRING((19 50, 19 50.01), (19 50.02, 19 50.03))', $writer->document($document));
    }

    #[Test]
    public function it_writes_single_segment_track_as_line_string(): void
    {
        $track = new Track(segments: [
            new TrackSegment([new Point(50.0, 19.0), new Point(50.1, 19.1)]),
            new TrackSegment([new Point(51.0, 19.0)]),
        ]);

        self::assertSame('LINESTRING(19 50, 19.1 50.1)', (new WktWriter())->track($track));
    }

    #[Test]
    public function it_writes_routes(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('route.gpx'));
        $writer = new WktWriter();

        self::assertSame('LINESTRING(19 50, 19 50.01, 19.01 50.01)', $writer->route($document->routes[0]));
        self::assertSame('MULTILINESTRING((19 50, 19 50.01, 19.01 50.01))', $writer->document($document, Scope::Routes));
        self::assertNull($writer->document($document));
    }

    #[Test]
    public function it_rejects_invalid_precision(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WktWriter(16);
    }
}
