<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * Raw content of a GPX <extensions> element, kept without interpretation.
 *
 * Every child element is serialized as a standalone XML fragment that declares
 * all namespaces it uses, so it can be loaded on its own. Text outside child
 * elements is not kept.
 */
final readonly class Extensions
{
    /**
     * @param list<string> $elements
     */
    public function __construct(
        public array $elements,
    ) {
    }
}
