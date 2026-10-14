<?php

declare(strict_types=1);

namespace GpxToolkit\Internal;

use DateTimeImmutable;
use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Exception\XmlParseException;
use GpxToolkit\Model\Bounds;
use GpxToolkit\Model\Copyright;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Email;
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
 * Builds the model from GPX 1.1 XML.
 *
 * @internal
 */
final class GpxReader
{
    public const string GPX_1_1_NAMESPACE = 'http://www.topografix.com/GPX/1/1';

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
        $root = $this->loadXml($content, $source);
        $this->namespace = $this->resolveNamespace($root, $source);

        $version = $this->attribute($root, 'version');
        if ('1.1' !== $version) {
            throw ValidationException::inDocument(
                sprintf('Unsupported GPX version "%s", only "1.1" is supported', $version ?? ''),
                $source,
                '/gpx',
            );
        }

        $children = $this->children($root);

        return new Document(
            $version,
            $this->attribute($root, 'creator') ?? '',
            isset($children->metadata) ? $this->metadata($children->metadata) : null,
            $this->points($children->wpt),
            array_map($this->route(...), $this->list($children->rte)),
            array_map($this->track(...), $this->list($children->trk)),
            null,
        );
    }

    private function loadXml(string $content, string $source): SimpleXMLElement
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $root = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        if (!$root instanceof SimpleXMLElement) {
            $error = $errors[0] ?? null;

            throw XmlParseException::malformed(
                $source,
                null === $error ? 'unknown XML parser error' : trim($error->message),
                $error?->line,
            );
        }

        return $root;
    }

    private function resolveNamespace(SimpleXMLElement $root, string $source): ?string
    {
        $element = dom_import_simplexml($root);
        if ('gpx' !== $element->localName) {
            throw ValidationException::inDocument(
                sprintf('Root element must be "gpx", "%s" given', $element->localName),
                $source,
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
            $source,
            '/gpx',
        );
    }

    private function children(SimpleXMLElement $element): SimpleXMLElement
    {
        return null === $this->namespace ? $element->children() : $element->children($this->namespace);
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function list(?SimpleXMLElement $elements): array
    {
        $list = [];
        foreach ($elements ?? [] as $element) {
            $list[] = $element;
        }

        return $list;
    }

    private function metadata(SimpleXMLElement $element): Metadata
    {
        $children = $this->children($element);

        return new Metadata(
            $this->text($children, 'name'),
            $this->text($children, 'desc'),
            isset($children->author) ? $this->person($children->author) : null,
            isset($children->copyright) ? $this->copyright($children->copyright) : null,
            $this->links($children->link),
            isset($children->time) ? new DateTimeImmutable((string) $children->time) : null,
            $this->text($children, 'keywords'),
            isset($children->bounds) ? $this->bounds($children->bounds) : null,
            null,
        );
    }

    private function person(SimpleXMLElement $element): Person
    {
        $children = $this->children($element);

        return new Person(
            $this->text($children, 'name'),
            isset($children->email) ? $this->email($children->email) : null,
            isset($children->link) ? $this->link($children->link) : null,
        );
    }

    private function email(SimpleXMLElement $element): Email
    {
        return new Email($this->attribute($element, 'id') ?? '', $this->attribute($element, 'domain') ?? '');
    }

    private function copyright(SimpleXMLElement $element): Copyright
    {
        $children = $this->children($element);

        return new Copyright(
            $this->attribute($element, 'author') ?? '',
            isset($children->year) ? (int) $children->year : null,
            $this->text($children, 'license'),
        );
    }

    private function bounds(SimpleXMLElement $element): Bounds
    {
        return new Bounds(
            (float) $this->attribute($element, 'minlat'),
            (float) $this->attribute($element, 'minlon'),
            (float) $this->attribute($element, 'maxlat'),
            (float) $this->attribute($element, 'maxlon'),
        );
    }

    /**
     * @return list<Link>
     */
    private function links(?SimpleXMLElement $elements): array
    {
        return array_map($this->link(...), $this->list($elements));
    }

    private function link(SimpleXMLElement $element): Link
    {
        $children = $this->children($element);

        return new Link(
            $this->attribute($element, 'href') ?? '',
            $this->text($children, 'text'),
            $this->text($children, 'type'),
        );
    }

    private function route(SimpleXMLElement $element): Route
    {
        $children = $this->children($element);

        return new Route(
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->links($children->link),
            isset($children->number) ? (int) $children->number : null,
            $this->text($children, 'type'),
            null,
            $this->points($children->rtept),
        );
    }

    private function track(SimpleXMLElement $element): Track
    {
        $children = $this->children($element);

        return new Track(
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->links($children->link),
            isset($children->number) ? (int) $children->number : null,
            $this->text($children, 'type'),
            null,
            array_map(
                fn (SimpleXMLElement $segment): TrackSegment => new TrackSegment(
                    $this->points($this->children($segment)->trkpt),
                    null,
                ),
                $this->list($children->trkseg),
            ),
        );
    }

    /**
     * @return list<Point>
     */
    private function points(?SimpleXMLElement $elements): array
    {
        return array_map($this->point(...), $this->list($elements));
    }

    private function point(SimpleXMLElement $element): Point
    {
        $children = $this->children($element);

        return new Point(
            (float) $this->attribute($element, 'lat'),
            (float) $this->attribute($element, 'lon'),
            $this->float($children, 'ele'),
            isset($children->time) ? new DateTimeImmutable((string) $children->time) : null,
            $this->float($children, 'magvar'),
            $this->float($children, 'geoidheight'),
            $this->text($children, 'name'),
            $this->text($children, 'cmt'),
            $this->text($children, 'desc'),
            $this->text($children, 'src'),
            $this->links($children->link),
            $this->text($children, 'sym'),
            $this->text($children, 'type'),
            isset($children->fix) ? FixType::from((string) $children->fix) : null,
            isset($children->sat) ? (int) $children->sat : null,
            $this->float($children, 'hdop'),
            $this->float($children, 'vdop'),
            $this->float($children, 'pdop'),
            $this->float($children, 'ageofdgpsdata'),
            isset($children->dgpsid) ? (int) $children->dgpsid : null,
            null,
        );
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

    private function text(SimpleXMLElement $children, string $name): ?string
    {
        $node = $children->{$name};

        return $node instanceof SimpleXMLElement && isset($children->{$name}) ? (string) $node : null;
    }

    private function float(SimpleXMLElement $children, string $name): ?float
    {
        $text = $this->text($children, $name);

        return null === $text ? null : (float) $text;
    }
}
