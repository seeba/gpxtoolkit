<?php

declare(strict_types=1);

namespace GpxToolkit\Middleware;

use GpxToolkit\Model\Document;

/**
 * Transforms a parsed document. Implementations must not modify the given document;
 * they return a new one or pass it on unchanged.
 */
interface Middleware
{
    /**
     * @param callable(Document): Document $next
     */
    public function handle(Document $document, callable $next): Document;
}
