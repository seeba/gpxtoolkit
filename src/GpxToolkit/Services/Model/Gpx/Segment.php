<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

readonly class Segment
{
    /**
     * @param GpxElementInterface[] $points
     */
    protected function __construct(
        public ?array $points = [],
        public ?Extensions $extensions = null
    ) {
    }

    public static function create(?array $points, ?Extensions $extensions = null): self
    {
        return new self($points, $extensions);
    }
}
