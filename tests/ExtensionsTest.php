<?php

declare(strict_types=1);

namespace GpxToolkit\Tests;

use GpxToolkit\Model\Extensions;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

final class ExtensionsTest extends TestCase
{
    #[Test]
    public function it_keeps_raw_extensions_with_namespaces(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('extensions.gpx'));
        $segment = $document->tracks[0]->segments[0];

        self::assertEquals(
            new Extensions(['<custom xmlns="urn:example:custom" level="document">value</custom>']),
            $document->extensions,
        );
        self::assertNull($document->tracks[0]->extensions);

        $pointExtensions = $segment->points[0]->extensions;
        self::assertNotNull($pointExtensions);
        self::assertCount(1, $pointExtensions->elements);
        $extension = new SimpleXMLElement($pointExtensions->elements[0]);
        $values = $extension->children('http://www.garmin.com/xmlschemas/TrackPointExtension/v1');
        self::assertSame('120', (string) $values->hr);
        self::assertSame('80', (string) $values->cad);

        self::assertSame(
            ['<gpxtpx:segmentNote xmlns:gpxtpx="http://www.garmin.com/xmlschemas/TrackPointExtension/v1">segment</gpxtpx:segmentNote>'],
            $segment->extensions?->elements,
        );
    }

    #[Test]
    public function it_keeps_extensions_of_every_element_type(): void
    {
        $document = (new Toolkit())->parseString(<<<'GPX'
            <gpx xmlns="http://www.topografix.com/GPX/1/1" xmlns:x="urn:x" version="1.1" creator="test">
                <metadata><extensions><x:m/></extensions></metadata>
                <wpt lat="0" lon="0"><extensions><x:w/></extensions></wpt>
                <rte><extensions><x:r/></extensions><rtept lat="0" lon="0"/></rte>
                <trk><extensions><x:t/></extensions></trk>
            </gpx>
            GPX);

        self::assertSame(['<x:m xmlns:x="urn:x"/>'], $document->metadata?->extensions?->elements);
        self::assertSame(['<x:w xmlns:x="urn:x"/>'], $document->waypoints[0]->extensions?->elements);
        self::assertSame(['<x:r xmlns:x="urn:x"/>'], $document->routes[0]->extensions?->elements);
        self::assertSame(['<x:t xmlns:x="urn:x"/>'], $document->tracks[0]->extensions?->elements);
    }

    #[Test]
    public function it_keeps_empty_extensions_element(): void
    {
        $document = (new Toolkit())->parseString(
            '<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="t"><extensions> text </extensions></gpx>',
        );

        self::assertEquals(new Extensions([]), $document->extensions);
    }
}
