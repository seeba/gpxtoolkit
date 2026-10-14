<?php

declare(strict_types=1);

namespace GpxToolkit\Tests;

use ArrayObject;
use DateTimeImmutable;
use GpxToolkit\Exception\GpxToolkitException;
use GpxToolkit\Exception\ReadException;
use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Exception\XmlParseException;
use GpxToolkit\Middleware\Middleware;
use GpxToolkit\Model\Copyright;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Email;
use GpxToolkit\Model\FixType;
use GpxToolkit\Model\Link;
use GpxToolkit\Model\Person;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use GpxToolkit\ToolkitOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolkitTest extends TestCase
{
    #[Test]
    public function it_reads_track_document_with_metadata(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('track.gpx'));

        self::assertSame('1.1', $document->version);
        self::assertSame('GpxToolkit fixtures', $document->creator);

        $metadata = $document->metadata;
        self::assertNotNull($metadata);
        self::assertSame('Track fixture', $metadata->name);
        self::assertSame('Single track with one segment', $metadata->description);
        self::assertEquals(
            new Person('Fixture Author', new Email('author', 'example.com'), new Link('https://example.com/author', 'Author page')),
            $metadata->author,
        );
        self::assertSame('author@example.com', $metadata->author?->email?->address());
        self::assertEquals(
            new Copyright('Fixture Author', 2026, 'https://creativecommons.org/publicdomain/zero/1.0/'),
            $metadata->copyright,
        );
        self::assertEquals([new Link('https://example.com/track', 'Track page', 'text/html')], $metadata->links);
        self::assertEquals(new DateTimeImmutable('2026-05-13T09:59:00Z'), $metadata->time);
        self::assertSame('fixture, track', $metadata->keywords);
        self::assertSame(50.002, $metadata->bounds?->maxLatitude);

        self::assertCount(1, $document->tracks);
        $track = $document->tracks[0];
        self::assertSame('Morning walk', $track->name);
        self::assertSame('comment', $track->comment);
        self::assertSame('Walk around the block', $track->description);
        self::assertSame('GPS device', $track->source);
        self::assertSame('https://example.com/walk', $track->links[0]->href);
        self::assertSame(1, $track->number);
        self::assertSame('walking', $track->type);
        self::assertCount(1, $track->segments);
        self::assertCount(3, $track->segments[0]->points);
        self::assertSame(205.0, $track->segments[0]->points[1]->elevation);
    }

    #[Test]
    public function it_maps_every_point_field(): void
    {
        $point = (new Toolkit())->parseFile(Fixtures::path('full-point.gpx'))->waypoints[0];

        self::assertSame(49.2321, $point->latitude);
        self::assertSame(19.9817, $point->longitude);
        self::assertSame(1987.5, $point->elevation);
        self::assertEquals(new DateTimeImmutable('2026-05-13T08:00:00.250Z'), $point->time);
        self::assertSame(4.5, $point->magneticVariation);
        self::assertSame(41.2, $point->geoidHeight);
        self::assertSame('Kasprowy Wierch', $point->name);
        self::assertSame('summit', $point->comment);
        self::assertSame('Summit waypoint', $point->description);
        self::assertSame('map', $point->source);
        self::assertEquals(
            [new Link('https://example.com/kasprowy', 'Kasprowy', 'text/html'), new Link('https://example.com/kasprowy-2')],
            $point->links,
        );
        self::assertSame('Summit', $point->symbol);
        self::assertSame('peak', $point->type);
        self::assertSame(FixType::ThreeD, $point->fix);
        self::assertSame(9, $point->satelliteCount);
        self::assertSame(0.9, $point->hdop);
        self::assertSame(1.2, $point->vdop);
        self::assertSame(1.5, $point->pdop);
        self::assertSame(2.5, $point->ageOfDgpsData);
        self::assertSame(17, $point->dgpsId);
    }

    #[Test]
    public function it_reads_routes(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('route.gpx'));

        self::assertSame([], $document->tracks);
        self::assertCount(1, $document->routes);
        self::assertSame('Planned route', $document->routes[0]->name);
        self::assertSame(2, $document->routes[0]->number);
        self::assertCount(3, $document->routes[0]->points);
        self::assertSame('Finish', $document->routes[0]->points[2]->name);
        self::assertNull($document->routes[0]->points[1]->time);
    }

    #[Test]
    public function it_keeps_track_and_segment_structure(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('multi-track-multi-segment.gpx'));

        self::assertCount(2, $document->tracks);
        self::assertCount(3, $document->tracks[0]->segments);
        self::assertCount(2, $document->tracks[0]->segments[1]->points);
        self::assertSame([], $document->tracks[0]->segments[2]->points);
        self::assertCount(1, $document->tracks[1]->segments[0]->points);
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function documentsWithoutTrack(): iterable
    {
        yield 'waypoints only' => ['waypoints-only.gpx', 2, 0, 0];
        yield 'metadata only' => ['metadata-only.gpx', 0, 0, 0];
        yield 'empty document' => ['empty-document.gpx', 0, 0, 0];
        yield 'track and route' => ['track-and-route.gpx', 0, 1, 1];
    }

    #[Test]
    #[DataProvider('documentsWithoutTrack')]
    public function it_accepts_valid_documents_regardless_of_content(string $fixture, int $waypoints, int $routes, int $tracks): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path($fixture));

        self::assertCount($waypoints, $document->waypoints);
        self::assertCount($routes, $document->routes);
        self::assertCount($tracks, $document->tracks);
    }

    #[Test]
    public function it_reads_gpx_namespace_with_any_prefix(): void
    {
        $document = (new Toolkit())->parseFile(Fixtures::path('namespace-prefixed.gpx'));

        self::assertSame('Prefixed', $document->metadata?->name);
        self::assertSame('Prefixed track', $document->tracks[0]->name);
        self::assertSame(201.0, $document->tracks[0]->segments[0]->points[1]->elevation);
    }

    #[Test]
    public function it_rejects_document_without_namespace_by_default(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('GPX root element has no namespace');

        (new Toolkit())->parseFile(Fixtures::path('namespace-missing.gpx'));
    }

    #[Test]
    public function it_reads_document_without_namespace_in_compatibility_mode(): void
    {
        $toolkit = new Toolkit(new ToolkitOptions(allowMissingNamespace: true));

        $document = $toolkit->parseFile(Fixtures::path('namespace-missing.gpx'));

        self::assertSame('No namespace', $document->tracks[0]->name);
        self::assertCount(2, $document->tracks[0]->segments[0]->points);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedDocuments(): iterable
    {
        yield 'other namespace' => ['namespace-other.gpx', 'Unsupported GPX namespace "urn:example:not-gpx"'];
        yield 'GPX 1.0' => ['gpx-1.0.gpx', 'Unsupported GPX namespace "http://www.topografix.com/GPX/1/0"'];
    }

    #[Test]
    #[DataProvider('unsupportedDocuments')]
    public function it_rejects_unsupported_documents(string $fixture, string $message): void
    {
        $toolkit = new Toolkit(new ToolkitOptions(allowMissingNamespace: true));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        $toolkit->parseFile(Fixtures::path($fixture));
    }

    #[Test]
    public function it_rejects_unsupported_version_and_root(): void
    {
        $toolkit = new Toolkit(new ToolkitOptions(allowMissingNamespace: true));

        try {
            $toolkit->parseString('<gpx version="1.0"/>');
            self::fail('Expected version to be rejected.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('Unsupported GPX version "1.0"', $exception->getMessage());
            self::assertSame('/gpx', $exception->elementPath);
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Root element must be "gpx", "kml" given');

        $toolkit->parseString('<kml/>');
    }

    #[Test]
    public function it_reads_identical_document_from_file_string_and_stream(): void
    {
        $toolkit = new Toolkit();
        $path = Fixtures::path('multi-track-multi-segment.gpx');
        $stream = fopen($path, 'rb');
        self::assertIsResource($stream);

        $fromFile = $toolkit->parseFile($path);
        $fromString = $toolkit->parseString(Fixtures::content('multi-track-multi-segment.gpx'));
        $fromStream = $toolkit->parseStream($stream);
        fclose($stream);

        self::assertEquals($fromFile, $fromString);
        self::assertEquals($fromFile, $fromStream);
    }

    #[Test]
    public function it_does_not_depend_on_file_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gpxtoolkit-');
        file_put_contents($path, Fixtures::content('track.gpx'));

        try {
            self::assertCount(1, (new Toolkit())->parseFile($path)->tracks);
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function it_reports_malformed_xml_with_source_and_line(): void
    {
        try {
            (new Toolkit())->parseFile(Fixtures::path('malformed.gpx'));
            self::fail('Expected malformed XML to be rejected.');
        } catch (XmlParseException $exception) {
            self::assertInstanceOf(GpxToolkitException::class, $exception);
            self::assertSame(Fixtures::path('malformed.gpx'), $exception->source);
            self::assertNotNull($exception->xmlLine);
            self::assertStringContainsString('Invalid XML in GPX source', $exception->getMessage());
        }
    }

    #[Test]
    public function it_restores_libxml_error_handling(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            try {
                (new Toolkit())->parseString('<gpx>');
            } catch (XmlParseException) {
            }

            self::assertFalse(libxml_use_internal_errors());
            self::assertSame([], libxml_get_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    #[Test]
    public function it_reports_missing_file_and_directory(): void
    {
        try {
            (new Toolkit())->parseFile('/nonexistent/route.gpx');
            self::fail('Expected missing file to be rejected.');
        } catch (ReadException $exception) {
            self::assertSame('/nonexistent/route.gpx', $exception->source);
        }

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('is not a regular file');

        (new Toolkit())->parseFile(__DIR__);
    }

    #[Test]
    public function it_requires_stream_resource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Toolkit())->parseStream('not a stream'); // @phpstan-ignore argument.type
    }

    #[Test]
    public function it_runs_middleware_in_order_per_instance(): void
    {
        /** @var ArrayObject<int, string> $calls */
        $calls = new ArrayObject();
        $recording = static fn (string $name): Middleware => new class($name, $calls) implements Middleware {
            /** @param ArrayObject<int, string> $calls */
            public function __construct(private string $name, private ArrayObject $calls)
            {
            }

            public function handle(Document $document, callable $next): Document
            {
                $this->calls[] = $this->name.':before';
                $result = $next($document);
                $this->calls[] = $this->name.':after';

                return $result;
            }
        };

        $plain = new Toolkit();
        $configured = $plain->withMiddleware($recording('first'), $recording('second'));

        $plain->parseFile(Fixtures::path('track.gpx'));
        self::assertSame([], $calls->getArrayCopy());

        $configured->parseFile(Fixtures::path('track.gpx'));
        self::assertSame(['first:before', 'second:before', 'second:after', 'first:after'], $calls->getArrayCopy());
    }
}
