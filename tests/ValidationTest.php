<?php

declare(strict_types=1);

namespace GpxToolkit\Tests;

use DateTimeImmutable;
use DateTimeZone;
use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'missing latitude' => ['<wpt lon="19"/>', '/gpx/wpt[1]', 'Missing required attribute "lat"'];
        yield 'latitude out of range' => ['<wpt lat="91" lon="19"/>', '/gpx/wpt[1]', 'Latitude must be between -90 and 90, 91 given'];
        yield 'longitude out of range' => ['<wpt lat="50" lon="-180.5"/>', '/gpx/wpt[1]', 'Longitude must be between -180 and 180, -180.5 given'];
        yield 'latitude not a number' => ['<wpt lat="abc" lon="19"/>', '/gpx/wpt[1]/@lat', 'Invalid decimal number "abc"'];
        yield 'latitude not finite' => ['<wpt lat="NaN" lon="19"/>', '/gpx/wpt[1]/@lat', 'Invalid decimal number "NaN"'];
        yield 'longitude in exponent notation' => ['<wpt lat="50" lon="1e2"/>', '/gpx/wpt[1]/@lon', 'Invalid decimal number "1e2"'];
        yield 'elevation not a number' => ['<wpt lat="50" lon="19"><ele>high</ele></wpt>', '/gpx/wpt[1]/ele', 'Invalid decimal number "high"'];
        yield 'time as free text' => ['<wpt lat="50" lon="19"><time>yesterday</time></wpt>', '/gpx/wpt[1]/time', 'Invalid date-time "yesterday"'];
        yield 'time with space separator' => ['<wpt lat="50" lon="19"><time>2026-05-13 10:00:00Z</time></wpt>', '/gpx/wpt[1]/time', 'expected xsd:dateTime'];
        yield 'time with impossible date' => ['<wpt lat="50" lon="19"><time>2026-02-30T10:00:00Z</time></wpt>', '/gpx/wpt[1]/time', 'Invalid date-time'];
        yield 'time with impossible hour' => ['<wpt lat="50" lon="19"><time>2026-05-13T25:00:00Z</time></wpt>', '/gpx/wpt[1]/time', 'Invalid date-time'];
        yield 'unknown fix' => ['<wpt lat="50" lon="19"><fix>4d</fix></wpt>', '/gpx/wpt[1]/fix', 'Invalid fix "4d"'];
        yield 'fractional satellite count' => ['<wpt lat="50" lon="19"><sat>1.5</sat></wpt>', '/gpx/wpt[1]/sat', 'Invalid integer "1.5"'];
        yield 'negative satellite count' => ['<wpt lat="50" lon="19"><sat>-1</sat></wpt>', '/gpx/wpt[1]', 'Satellite count must not be negative'];
        yield 'DGPS id out of range' => ['<wpt lat="50" lon="19"><dgpsid>1024</dgpsid></wpt>', '/gpx/wpt[1]', 'DGPS id must be between 0 and 1023'];
        yield 'magnetic variation out of range' => ['<wpt lat="50" lon="19"><magvar>360</magvar></wpt>', '/gpx/wpt[1]', 'Magnetic variation'];
        yield 'link without href' => ['<wpt lat="50" lon="19"><link/></wpt>', '/gpx/wpt[1]/link[1]', 'Missing required attribute "href"'];
        yield 'negative track number' => ['<trk><number>-1</number></trk>', '/gpx/trk[1]', 'Number must not be negative'];
        yield 'route point' => ['<rte><rtept lat="50" lon="19"/><rtept lat="95" lon="19"/></rte>', '/gpx/rte[1]/rtept[2]', 'Latitude must be between'];
        yield 'track point in second segment' => [
            '<trk><trkseg><trkpt lat="50" lon="19"/></trkseg><trkseg><trkpt lat="50" lon="19"><ele>x</ele></trkpt></trkseg></trk>',
            '/gpx/trk[1]/trkseg[2]/trkpt[1]/ele',
            'Invalid decimal number "x"',
        ];
        yield 'bounds minimum above maximum' => [
            '<metadata><bounds minlat="51" minlon="19" maxlat="50" maxlon="20"/></metadata>',
            '/gpx/metadata/bounds',
            'Bounds minimum must not exceed maximum',
        ];
        yield 'bounds missing attribute' => [
            '<metadata><bounds minlat="50" minlon="19" maxlat="51"/></metadata>',
            '/gpx/metadata/bounds',
            'Missing required attribute "maxlon"',
        ];
        yield 'email without domain' => ['<metadata><author><email id="me"/></author></metadata>', '/gpx/metadata/author/email', 'Missing required attribute "domain"'];
        yield 'copyright without author' => ['<metadata><copyright/></metadata>', '/gpx/metadata/copyright', 'Missing required attribute "author"'];
        yield 'invalid copyright year' => ['<metadata><copyright author="me"><year>26</year></copyright></metadata>', '/gpx/metadata/copyright/year', 'Invalid year "26"'];
        yield 'invalid metadata time' => ['<metadata><time>13.05.2026</time></metadata>', '/gpx/metadata/time', 'Invalid date-time "13.05.2026"'];
    }

    #[Test]
    #[DataProvider('invalidDocuments')]
    public function it_rejects_invalid_values_with_element_path(string $body, string $path, string $message): void
    {
        try {
            (new Toolkit())->parseString(self::gpx($body), 'inline.gpx');
            self::fail('Expected the document to be rejected.');
        } catch (ValidationException $exception) {
            self::assertSame('inline.gpx', $exception->source);
            self::assertSame($path, $exception->elementPath);
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertStringContainsString('source "inline.gpx", element '.$path, $exception->getMessage());
        }
    }

    /**
     * @return iterable<int, array{string}>
     */
    public static function invalidFixtures(): iterable
    {
        yield ['invalid-coordinates.gpx'];
        yield ['invalid-time.gpx'];
        yield ['invalid-fix.gpx'];
    }

    #[Test]
    #[DataProvider('invalidFixtures')]
    public function it_rejects_invalid_reference_fixtures(string $fixture): void
    {
        $this->expectException(ValidationException::class);

        (new Toolkit())->parseFile(Fixtures::path($fixture));
    }

    #[Test]
    public function it_accepts_valid_edge_values(): void
    {
        $document = (new Toolkit())->parseString(self::gpx(<<<'XML'
            <wpt lat=" 90 " lon="-180"><ele>0</ele><time>2026-05-13T10:00:00</time><magvar>359.9</magvar><sat>0</sat><dgpsid>1023</dgpsid></wpt>
            <wpt lat="-90" lon="180"><ele>-.5</ele><time>2026-05-13T10:00:00.123456789+02:00</time></wpt>
            <wpt lat="0" lon="0"><time>2024-02-29T23:59:59-05:30</time><fix> dgps </fix></wpt>
            XML));

        [$first, $second, $third] = $document->waypoints;
        self::assertSame(90.0, $first->latitude);
        self::assertSame(0.0, $first->elevation);
        self::assertEquals(new DateTimeImmutable('2026-05-13T10:00:00Z'), $first->time);
        self::assertSame('+00:00', $first->time?->format('P'));
        self::assertSame(-0.5, $second->elevation);
        self::assertSame('2026-05-13T08:00:00.123456Z', $second->time?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('-05:30', $third->time?->format('P'));
        self::assertSame('dgps', $third->fix?->value);
    }

    #[Test]
    public function it_keeps_missing_measurements_as_null_and_zero_as_measurement(): void
    {
        $points = (new Toolkit())->parseFile(Fixtures::path('missing-time-elevation.gpx'))->tracks[0]->segments[0]->points;

        self::assertSame(0.0, $points[0]->elevation);
        self::assertNull($points[1]->elevation);
        self::assertNull($points[1]->time);
        self::assertSame(10.0, $points[2]->elevation);
        self::assertNull($points[3]->elevation);
        self::assertNotNull($points[3]->time);
    }

    private static function gpx(string $body): string
    {
        return '<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="test">'.$body.'</gpx>';
    }
}
