<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

use GpxToolkit\Internal\Assert;

/**
 * GPX 1.1 rteType: an ordered list of points leading to a destination.
 */
final readonly class Route
{
    /**
     * @param list<Link>  $links
     * @param list<Point> $points
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
        public array $points = [],
    ) {
        Assert::nonNegative($number, 'number');
    }

    /**
     * @param list<Point> $points
     */
    public function withPoints(array $points): self
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
            $points,
        );
    }
}
