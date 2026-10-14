<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 copyrightType.
 */
final readonly class Copyright
{
    public function __construct(
        public string $author,
        public ?int $year = null,
        public ?string $license = null,
    ) {
    }
}
