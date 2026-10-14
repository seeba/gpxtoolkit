<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Geometry;

use GpxToolkit\Geometry\WktWriter;
use GpxToolkit\Model\Point;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WktWriterTest extends TestCase
{
    #[Test]
    public function it_writes_longitude_before_latitude(): void
    {
        $writer = new WktWriter();

        self::assertSame('POINT(19.500000 50.250000)', $writer->point(new Point(50.25, 19.5)));
        self::assertSame('LINESTRING(20 10, 21 11)', $writer->lineString([new Point(10.0, 20.0), new Point(11.0, 21.0)]));
    }

    #[Test]
    public function it_writes_each_track_from_its_own_points(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('multi-track-multi-segment.gpx'));
        $writer = new WktWriter();

        self::assertSame('LINESTRING(19 50, 19 50.01, 19 50.02, 19 50.03)', $writer->trackLineString($document->tracks[0]));
        self::assertSame('LINESTRING(19 50.03)', $writer->trackLineString($document->tracks[1]));
    }
}
