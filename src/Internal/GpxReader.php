<?php

declare(strict_types=1);

namespace GpxToolkit\Internal;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use GpxToolkit\Exception\LimitExceededException;
use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Exception\XmlParseException;
use GpxToolkit\Model\Bounds;
use GpxToolkit\Model\Copyright;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Email;
use GpxToolkit\Model\Extensions;
use GpxToolkit\Model\FixType;
use GpxToolkit\Model\Link;
use GpxToolkit\Model\Metadata;
use GpxToolkit\Model\Person;
use GpxToolkit\Model\Point;
use GpxToolkit\Model\Route;
use GpxToolkit\Model\Track;
use GpxToolkit\Model\TrackSegment;
use GpxToolkit\ToolkitOptions;
use SimpleXMLElement;

/**
 * Builds the model from GPX 1.1 XML. One instance reads one document.
 *
 * @internal
 */
final class GpxReader
{
    public const string GPX_1_1_NAMESPACE = 'http://www.topografix.com/GPX/1/1';

    private const string DECIMAL = '/^[+-]?(\d+(\.\d*)?|\.\d+)$/';
    private const string INTEGER = '/^[+-]?\d+$/';
    private const string DATE_TIME = '/^(-?\d{4,})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(Z|[+-](\d{2}):(\d{2}))?$/';
    private const string YEAR = '/^(-?\d{4,})(Z|[+-]\d{2}:\d{2})?$/';

    private string $source = '';

    /**
     * Namespace of GPX elements in the document being read; null in compatibility mode
     * for documents without a namespace.
     */
    private ?string $namespace = null;

    public function __construct(
        private readonly ToolkitOptions $options,
    ) {
    }

    public function read(string $content, string $source): Document
    {
        $this->source = $source;

        if (null !== $this->options->maxBytes && strlen($content) > $this->options->maxBytes) {
            throw LimitExceededException::size($source, $this->options->maxBytes);
        }

        $this->assertNoDoctypeInProlog($content);
        $root = $this->loadXml($content);
        $this->namespace = $this->resolveNamespace($root);

        $version = $this->attribute($root, 'version');
        if ('1.1' !== $version) {
            throw ValidationException::inDocument(
                sprintf('Unsupported GPX version "%s", only "1.1" is supported', $version ?? ''),
                $source,
                '/gpx',
            );
        }

        $this->assertPointLimit($root);

        $children = $this->children($root);
        $metadata = $this->first($children, 'metadata');

        return new Document(
            $version,
            $this->attribute($root, 'creator') ?? '',
            null === $metadata ? null : $this->metadata($metadata, '/gpx/metadata'),
            $this->collection($children, 'wpt', '/gpx', $this->point(...)),
            $this->collection($children, 'rte', '/gpx', $this->route(...)),
            $this->collection($children, 'trk', '/gpx', $this->track(...)),
            $this->extensions($children),
        );
    }

    private function assertNoDoctypeInProlog(string $content): void
    {
        $offset = str_starts_with($content, "\xEF\xBB\xBF") ? 3 : 0;
        while (1 === preg_match('/\G\s*(?:<\?.*?\?>|<!--.*?-->)/s', $content, $match, 0, $offset)) {
            $offset += strlen($match[0]);
        }

        if (1 === preg_match('/\G\s*<!DOCTYPE/', $content, $match, 0, $offset)) {
            throw XmlParseException::doctype($this->source);
        }
    }

    private function loadXml(string $content): SimpleXMLElement
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            // No LIBXML_NOENT, LIBXML_DTDLOAD or LIBXML_PARSEHUGE: entities stay unexpanded,
            // external resources are never loaded and libxml keeps its default size limits.
            $root = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        if (!$root instanceof SimpleXMLElement) {
            $error = $errors[0] ?? null;

            throw XmlParseException::malformed(
                $this->source,
                null === $error ? 'unknown XML parser error' : trim($error->message),
                $error?->line,
            );
        }

        if (null !== dom_import_simplexml($root)->ownerDocument?->doctype) {
            throw XmlParseException::doctype($this->source);
        }

        return $root;
    }

    private function resolveNamespace(SimpleXMLElement $root): ?string
    {
        $element = dom_import_simplexml($root);
        if ('gpx' !== $element->localName) {
            throw ValidationException::inDocument(
                sprintf('Root element must be "gpx", "%s" given', $element->localName),
                $this->source,
                '/'.$element->localName,
            );
        }

        $namespace = $element->namespaceURI;
        if (self::GPX_1_1_NAMESPACE === $namespace) {
            return $namespace;
        }

        if (null === $namespace && $this->options->allowMissingNamespace) {
            return null;
        }

        throw ValidationException::inDocument(
            null === $namespace
                ? sprintf('GPX root element has no namespace, expected "%s"', self::GPX_1_1_NAMESPACE)
                : sprintf('Unsupported GPX namespace "%s", expected "%s"', $namespace, self::GPX_1_1_NAMESPACE),
            $this->source,
            '/gpx',
        );
    }

    private function assertPointLimit(SimpleXMLElement $root): void
    {
        $maxPoints = $this->options->maxPoints;
        $document = dom_import_simplexml($root)->ownerDocument;
        if (null === $maxPoints || null === $document) {
            return;
        }

        $xpath = new DOMXPath($document);
        $prefix = '';
        if (null !== $this->namespace) {
            $xpath->registerNamespace('gpx', $this->namespace);
            $prefix = 'gpx:';
        }

        // count() is evaluated by libxml without materializing node objects.
        $count = $xpath->evaluate(sprintf('count(//%1$swpt | //%1$srtept | //%1$strkpt)', $prefix));
        $count = is_float($count) ? (int) $count : 0;
        if ($count > $maxPoints) {
            throw LimitExceededException::points($this->source, $maxPoints, $count);
        }
    }

    private function metadata(SimpleXMLElement $element, string $path): Metadata
    {
        $children = $this->children($element);
        $author = $this->first($children, 'author');
        $copyright = $this->first($children, 'copyright');
        $bounds = $this->first($children, 'bounds');

        return new Metadata(
            $this->text($children, 'name'),
            $this->text($children, 'desc'),
            null === $author ? null : $this->person($author, $path.'/author'),
            null === $copyright ? null : $this->copyright($copyright, $path.'/copyright'),
            $this->collection($children, 'link', $path, $this->link(...)),
            $this->dateTime($children, 'time', $path),
            $this->text($children, 'keywords'),
            null === $bounds ? null : $this->bounds($bounds, $path.'/bounds'),
            $this->extensions($children),
        );
    }

    private function person(SimpleXMLElement $element, string $path): Person
    {
        $children = $this->children($element);
        $email = $this->first($children, 'email');
        $link = $this->first($children, 'link');

        return new Person(
            $this->text($children, 'name'),
            null === $email ? null : new Email(
                $this->requiredAttribute($email, 'id', $path.'/email'),
                $this->requiredAttribute($email, 'domain', $path.'/email'),
            ),
            null === $link ? null : $this->link($link, $path.'/link'),
        );
    }

    private function copyright(SimpleXMLElement $element, string $path): Copyright
    {
        $children = $this->children($element);
        $year = $this->text($children, 'year');
        if (null !== $year) {
            $year = trim($year);
            if (1 !== preg_match(self::YEAR, $year, $match)) {
                $this->fail(sprintf('Invalid year "%s"', $year), $path.'/year');
            }
            $year = (int) $match[1];
        }

        return new Copyright(
            $this->requiredAttribute($element, 'author', $path),
            $year,
            $this->text($children, 'license'),
        );
    }

    private function bounds(SimpleXMLElement $element, string $path): Bounds
    {
        return $this->build($path, fn (): Bounds => new Bounds(
            $this->decimalAttribute($element, 'minlat', $path),
            $this->decimalAttribute($element, 'minlon', $path),
            $this->decimalAttribute($element, 'maxlat', $path),
            $this->decimalAttribute($element, 'maxlon', $path),
        ));
    }

    private function link(SimpleXMLElement $element, string $path): Link
    {
        $children = $this->children($element);

        return new Link(
            $this->requiredAttribute($element, 'href', $path),
            $this->text($children, 'text'),
            $this->text($children, 'type'),
        );
    }

    private function route(SimpleXMLElement $element, string $path): Route
    {
        $children = $this->children($element);

        return $this->build($path, fn (): Route => new Route(
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->collection($children, 'link', $path, $this->link(...)),
            $this->integer($children, 'number', $path),
            $this->text($children, 'type'),
            $this->extensions($children),
            $this->collection($children, 'rtept', $path, $this->point(...)),
        ));
    }

    private function track(SimpleXMLElement $element, string $path): Track
    {
        $children = $this->children($element);

        return $this->build($path, fn (): Track => new Track(
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->collection($children, 'link', $path, $this->link(...)),
            $this->integer($children, 'number', $path),
            $this->text($children, 'type'),
            $this->extensions($children),
            $this->collection($children, 'trkseg', $path, $this->segment(...)),
        ));
    }

    private function segment(SimpleXMLElement $element, string $path): TrackSegment
    {
        $children = $this->children($element);

        return new TrackSegment(
            $this->collection($children, 'trkpt', $path, $this->point(...)),
            $this->extensions($children),
        );
    }

    private function point(SimpleXMLElement $element, string $path): Point
    {
        $children = $this->children($element);
        $fix = $this->text($children, 'fix');
        if (null !== $fix) {
            $fix = FixType::tryFrom(trim($fix)) ?? $this->fail(sprintf('Invalid fix "%s"', $fix), $path.'/fix');
        }

        return $this->build($path, fn (): Point => new Point(
            $this->decimalAttribute($element, 'lat', $path),
            $this->decimalAttribute($element, 'lon', $path),
            $this->decimal($children, 'ele', $path),
            $this->dateTime($children, 'time', $path),
            $this->decimal($children, 'magvar', $path),
            $this->decimal($children, 'geoidheight', $path),
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->collection($children, 'link', $path, $this->link(...)),
            $this->text($children, 'sym'),
            $this->text($children, 'type'),
            $fix,
            $this->integer($children, 'sat', $path),
            $this->decimal($children, 'hdop', $path),
            $this->decimal($children, 'vdop', $path),
            $this->decimal($children, 'pdop', $path),
            $this->decimal($children, 'ageofdgpsdata', $path),
            $this->integer($children, 'dgpsid', $path),
            $this->extensions($children),
        ));
    }

    private function extensions(SimpleXMLElement $children): ?Extensions
    {
        $extensions = $this->first($children, 'extensions');
        if (null === $extensions) {
            return null;
        }

        $elements = [];
        foreach (dom_import_simplexml($extensions)->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            // Importing into an empty document re-declares every namespace the fragment uses.
            $fragment = new DOMDocument();
            $fragment->appendChild($fragment->importNode($node, true));
            $elements[] = (string) $fragment->saveXML($fragment->documentElement);
        }

        return new Extensions($elements);
    }

    /**
     * @template T
     *
     * @param callable(SimpleXMLElement, string): T $map
     *
     * @return list<T>
     */
    private function collection(SimpleXMLElement $children, string $name, string $parentPath, callable $map): array
    {
        $items = [];
        $elements = $this->first($children, $name);
        if (null === $elements) {
            return [];
        }

        $index = 0;
        foreach ($elements as $element) {
            $items[] = $map($element, sprintf('%s/%s[%d]', $parentPath, $name, ++$index));
        }

        return $items;
    }

    /**
     * Adds document context to invariant violations reported by model constructors.
     *
     * @template T
     *
     * @param callable(): T $factory
     *
     * @return T
     */
    private function build(string $path, callable $factory): mixed
    {
        try {
            return $factory();
        } catch (ValidationException $exception) {
            if (null !== $exception->source) {
                throw $exception;
            }

            throw ValidationException::inDocument(rtrim($exception->getMessage(), '.'), $this->source, $path, $exception);
        }
    }

    private function children(SimpleXMLElement $element): SimpleXMLElement
    {
        return null === $this->namespace ? $element->children() : $element->children($this->namespace);
    }

    private function first(SimpleXMLElement $children, string $name): ?SimpleXMLElement
    {
        $node = $children->{$name};

        return $node instanceof SimpleXMLElement && isset($children->{$name}) ? $node : null;
    }

    private function text(SimpleXMLElement $children, string $name): ?string
    {
        $node = $this->first($children, $name);

        return null === $node ? null : (string) $node;
    }

    private function decimal(SimpleXMLElement $children, string $name, string $parentPath): ?float
    {
        $value = $this->text($children, $name);

        return null === $value ? null : $this->parseDecimal($value, $parentPath.'/'.$name);
    }

    private function integer(SimpleXMLElement $children, string $name, string $parentPath): ?int
    {
        $value = $this->text($children, $name);
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        $integer = 1 === preg_match(self::INTEGER, $value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if (false === $integer) {
            $this->fail(sprintf('Invalid integer "%s"', $value), $parentPath.'/'.$name);
        }

        return $integer;
    }

    private function dateTime(SimpleXMLElement $children, string $name, string $parentPath): ?DateTimeImmutable
    {
        $value = $this->text($children, $name);
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        $path = $parentPath.'/'.$name;
        if (1 !== preg_match(self::DATE_TIME, $value, $match)
            || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            || (int) $match[4] > 23 || (int) $match[5] > 59 || (int) $match[6] > 59
            || (isset($match[9], $match[10]) && ((int) $match[9] > 14 || (int) $match[10] > 59))
        ) {
            $this->fail(sprintf('Invalid date-time "%s", expected xsd:dateTime', $value), $path);
        }

        // Time without a zone is UTC by the GPX specification; fractions beyond microseconds are truncated.
        $normalized = sprintf(
            '%s-%s-%sT%s:%s:%s.%s%s',
            $match[1],
            $match[2],
            $match[3],
            $match[4],
            $match[5],
            $match[6],
            str_pad(substr($match[7] ?? '', 0, 6), 6, '0'),
            ($match[8] ?? '') === '' ? 'Z' : $match[8],
        );

        try {
            return new DateTimeImmutable($normalized, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw ValidationException::inDocument(sprintf('Invalid date-time "%s"', $value), $this->source, $path, $exception);
        }
    }

    private function decimalAttribute(SimpleXMLElement $element, string $name, string $path): float
    {
        return $this->parseDecimal($this->requiredAttribute($element, $name, $path), $path.'/@'.$name);
    }

    private function requiredAttribute(SimpleXMLElement $element, string $name, string $path): string
    {
        return $this->attribute($element, $name) ?? $this->fail(sprintf('Missing required attribute "%s"', $name), $path);
    }

    /**
     * GPX attributes are never namespaced; plain $element['name'] would look them up
     * in the namespace the element was selected with.
     */
    private function attribute(SimpleXMLElement $element, string $name): ?string
    {
        $attributes = $element->attributes('');

        return isset($attributes[$name]) ? (string) $attributes[$name] : null;
    }

    private function parseDecimal(string $value, string $path): float
    {
        $value = trim($value);
        if (1 !== preg_match(self::DECIMAL, $value)) {
            $this->fail(sprintf('Invalid decimal number "%s"', $value), $path);
        }

        return (float) $value;
    }

    private function fail(string $message, string $path): never
    {
        throw ValidationException::inDocument($message, $this->source, $path);
    }
}
