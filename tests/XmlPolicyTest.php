<?php

declare(strict_types=1);

namespace GpxToolkit\Tests;

use GpxToolkit\Exception\LimitExceededException;
use GpxToolkit\Exception\ReadException;
use GpxToolkit\Exception\XmlParseException;
use GpxToolkit\Tests\Fixtures\Fixtures;
use GpxToolkit\Toolkit;
use GpxToolkit\ToolkitOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class XmlPolicyTest extends TestCase
{
    private const string MINIMAL = '<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="test"/>';

    #[Test]
    public function it_rejects_doctype_with_entities_without_resolving_them(): void
    {
        try {
            (new Toolkit())->parseFile(Fixtures::path('doctype-entities.gpx'));
            self::fail('Expected DOCTYPE to be rejected.');
        } catch (XmlParseException $exception) {
            self::assertStringContainsString('contains a DOCTYPE declaration', $exception->getMessage());
            self::assertStringNotContainsString('root:', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function doctypeVariants(): iterable
    {
        yield 'after BOM, comment and processing instruction' => [
            "\xEF\xBB\xBF<?xml version=\"1.0\"?>\n<!-- c --><?pi x?>\n<!DOCTYPE gpx>".self::MINIMAL,
        ];
        yield 'external DTD' => ['<!DOCTYPE gpx SYSTEM "http://example.invalid/gpx.dtd">'.self::MINIMAL];
        yield 'UTF-16 document' => [(string) mb_convert_encoding(
            '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE gpx [<!ENTITY e "x">]>'.self::MINIMAL,
            'UTF-16LE',
            'UTF-8',
        )];
    }

    #[Test]
    #[DataProvider('doctypeVariants')]
    public function it_rejects_doctype_anywhere_in_prolog(string $xml): void
    {
        $this->expectException(XmlParseException::class);
        $this->expectExceptionMessage('contains a DOCTYPE declaration');

        (new Toolkit())->parseString($xml);
    }

    #[Test]
    public function it_accepts_doctype_text_inside_prolog_comment(): void
    {
        $document = (new Toolkit())->parseString('<?xml version="1.0"?><!-- <!DOCTYPE gpx> -->'.self::MINIMAL);

        self::assertSame('1.1', $document->version);
    }

    #[Test]
    public function it_enforces_size_limit_for_every_source(): void
    {
        $toolkit = new Toolkit(new ToolkitOptions(maxBytes: 100));
        $content = Fixtures::content('track.gpx');
        $stream = fopen('php://memory', 'r+b');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        foreach ([
            static fn () => $toolkit->parseString($content),
            static fn () => $toolkit->parseFile(Fixtures::path('track.gpx')),
            static fn () => $toolkit->parseStream($stream),
        ] as $parse) {
            try {
                $parse();
                self::fail('Expected the size limit to be enforced.');
            } catch (LimitExceededException $exception) {
                self::assertSame(100, $exception->limit);
            }
        }

        self::assertSame(101, ftell($stream), 'Stream must not be read past the limit.');
        fclose($stream);

        self::assertCount(0, (new Toolkit(new ToolkitOptions(maxBytes: null)))->parseString($content)->waypoints);
    }

    #[Test]
    public function it_enforces_point_limit_across_waypoints_routes_and_tracks(): void
    {
        $limited = new Toolkit(new ToolkitOptions(maxPoints: 5));

        self::assertCount(1, $limited->parseFile(Fixtures::path('route.gpx'))->routes);

        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage('contains 6 points, the limit is 5');

        $limited->parseString(
            '<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="t">'
            .'<wpt lat="0" lon="0"/><wpt lat="0" lon="0"/>'
            .'<rte><rtept lat="0" lon="0"/><rtept lat="0" lon="0"/></rte>'
            .'<trk><trkseg><trkpt lat="0" lon="0"/><trkpt lat="0" lon="0"/></trkseg></trk></gpx>',
        );
    }

    #[Test]
    public function it_counts_points_in_compatibility_mode(): void
    {
        $this->expectException(LimitExceededException::class);

        (new Toolkit(new ToolkitOptions(allowMissingNamespace: true, maxPoints: 1)))
            ->parseFile(Fixtures::path('namespace-missing.gpx'));
    }

    #[Test]
    public function it_rejects_invalid_limits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ToolkitOptions(maxPoints: 0);
    }

    #[Test]
    public function it_reports_unreadable_file(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('File permissions are not enforced for root.');
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'gpxtoolkit-');
        file_put_contents($path, self::MINIMAL);
        chmod($path, 0o000);

        try {
            (new Toolkit())->parseFile($path);
            self::fail('Expected unreadable file to be rejected.');
        } catch (ReadException $exception) {
            self::assertSame($path, $exception->source);
            self::assertStringContainsString('could not be read', $exception->getMessage());
        } finally {
            chmod($path, 0o600);
            unlink($path);
        }
    }
}
