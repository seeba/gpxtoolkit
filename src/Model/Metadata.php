<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

use DateTimeImmutable;

/**
 * GPX 1.1 metadataType.
 */
final readonly class Metadata
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?Person $author = null,
        public ?Copyright $copyright = null,
        public array $links = [],
        public ?DateTimeImmutable $time = null,
        public ?string $keywords = null,
        public ?Bounds $bounds = null,
        public ?Extensions $extensions = null,
    ) {
    }
}
