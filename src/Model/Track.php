<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 trkType: a recorded path made of one or more segments.
 */
final readonly class Track
{
    /**
     * @param list<Link>         $links
     * @param list<TrackSegment> $segments
     */
    public function __construct(
        public ?string $name = null,
        public ?string $comment = null,
        public ?string $description = null,
        public ?string $source = null,
        public array $links = [],
        public ?int $number = null,
        public ?string $type = null,
        public ?Extensions $extensions = null,
        public array $segments = [],
    ) {
    }

    /**
     * @param list<TrackSegment> $segments
     */
    public function withSegments(array $segments): self
    {
        return new self(
            $this->name,
            $this->comment,
            $this->description,
            $this->source,
            $this->links,
            $this->number,
            $this->type,
            $this->extensions,
            $segments,
        );
    }
}
