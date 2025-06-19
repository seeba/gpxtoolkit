<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

abstract readonly class PointsCollection
{
    protected function __construct(
        public string $name,
        public ?string $comment = null,
        public ?string $description = null,
        public ?string $source = null,
        /**
         * @var GpxElementInterface[]
         */
        public array $links = [],
        public ?int $number = null,
        public ?string $type = null,
        public ?Extensions $extensions = null,
    ) {
    }
}
