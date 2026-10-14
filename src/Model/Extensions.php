<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * Raw content of a GPX <extensions> element, kept without interpretation.
 */
final readonly class Extensions
{
    public function __construct(
        public string $xml,
    ) {
    }
}
