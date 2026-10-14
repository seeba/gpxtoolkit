<?php

declare(strict_types=1);

namespace GpxToolkit\Tests\Middleware;

use GpxToolkit\Middleware\ReducePointsMiddleware;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReducePointsMiddlewareTest extends TestCase
{
    #[Test]
    public function it_reduces_straight_track_to_first_and_last_point(): void
    {
        $document = (new Toolkit(middleware: [new ReducePointsMiddleware()]))->parseString(<<<'GPX'
            <gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="test">
                <trk>
                    <trkseg>
                        <trkpt lat="50.0" lon="19.0" />
                        <trkpt lat="50.000005" lon="19.000005" />
                        <trkpt lat="50.000010" lon="19.000010" />
                    </trkseg>
                </trk>
            </gpx>
            GPX);

        $points = $document->tracks[0]->segments[0]->points;
        self::assertCount(2, $points);
        self::assertSame(50.0, $points[0]->latitude);
        self::assertSame(50.000010, $points[1]->latitude);
    }

    #[Test]
    public function it_keeps_loop_shape_and_other_document_data(): void
    {
        $toolkit = new Toolkit(middleware: [new ReducePointsMiddleware()]);
        $original = (new Toolkit())->parseFile(Fixtures::path('loop.gpx'));

        $reduced = $toolkit->parseFile(Fixtures::path('loop.gpx'));

        self::assertCount(5, $reduced->tracks[0]->segments[0]->points);
        self::assertSame($original->tracks[0]->name, $reduced->tracks[0]->name);
        self::assertEquals($original->metadata, $reduced->metadata);
    }
}
