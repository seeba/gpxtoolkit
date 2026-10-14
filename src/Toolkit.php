<?php

declare(strict_types=1);

namespace GpxToolkit;

use GpxToolkit\Exception\ReadException;
use GpxToolkit\Internal\GpxReader;
use GpxToolkit\Middleware\Middleware;
use GpxToolkit\Model\Document;
use InvalidArgumentException;

/**
 * Entry point of the library: reads GPX 1.1 from a file, string or stream and passes
 * the resulting document through the configured middleware.
 *
 * Each instance keeps its own configuration; instances never share state.
 */
final readonly class Toolkit
{
    /** @var list<Middleware> */
    private array $middleware;

    /**
     * @param iterable<Middleware> $middleware applied in the given order
     */
    public function __construct(
        private ToolkitOptions $options = new ToolkitOptions(),
        iterable $middleware = [],
    ) {
        $list = [];
        foreach ($middleware as $item) {
            $list[] = $item;
        }
        $this->middleware = $list;
    }

    public function withMiddleware(Middleware ...$middleware): self
    {
        return new self($this->options, [...$this->middleware, ...$middleware]);
    }

    public function parseFile(string $path): Document
    {
        if (!file_exists($path)) {
            throw ReadException::fileNotFound($path);
        }
        if (!is_file($path)) {
            throw ReadException::notAFile($path);
        }

        $stream = @fopen($path, 'rb');
        if (false === $stream) {
            throw ReadException::unreadable($path, error_get_last()['message'] ?? null);
        }

        try {
            return $this->parseStream($stream, $path);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param string $sourceName used in error messages only
     */
    public function parseString(string $xml, string $sourceName = 'string'): Document
    {
        return $this->process((new GpxReader($this->options))->read($xml, $sourceName));
    }

    /**
     * Reads the stream from its current position to the end. The stream is not closed.
     *
     * @param resource $stream
     * @param string   $sourceName used in error messages only
     */
    public function parseStream($stream, string $sourceName = 'stream'): Document
    {
        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new InvalidArgumentException('Expected an open stream resource.');
        }

        $content = @stream_get_contents($stream);
        if (false === $content) {
            throw ReadException::unreadable($sourceName, error_get_last()['message'] ?? null);
        }

        return $this->parseString($content, $sourceName);
    }

    private function process(Document $document): Document
    {
        $next = static fn (Document $document): Document => $document;

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = static fn (Document $document): Document => $middleware->handle($document, $next);
        }

        return $next($document);
    }
}
