<?php

declare(strict_types=1);

namespace GpxToolkit;

/**
 * Parser configuration of a single Toolkit instance.
 */
final readonly class ToolkitOptions
{
    /**
     * @param bool $allowMissingNamespace compatibility mode: accept documents whose elements have no namespace
     *                                    and read them as GPX 1.1
     */
    public function __construct(
        public bool $allowMissingNamespace = false,
    ) {
    }
}
