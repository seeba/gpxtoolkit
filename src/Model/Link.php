<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 linkType.
 */
final readonly class Link
{
    public function __construct(
        public string $href,
        public ?string $text = null,
        public ?string $type = null,
    ) {
    }
}
