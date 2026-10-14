<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 trksegType: a continuous span of track points.
 */
final readonly class TrackSegment
{
    /**
     * @param list<Point> $points
     */
    public function __construct(
        public array $points = [],
        public ?Extensions $extensions = null,
    ) {
    }

    /**
     * @param list<Point> $points
     */
    public function withPoints(array $points): self
    {
        return new self($points, $this->extensions);
    }
}
