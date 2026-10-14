<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 boundsType, in decimal degrees (WGS 84).
 */
final readonly class Bounds
{
    public function __construct(
        public float $minLatitude,
        public float $minLongitude,
        public float $maxLatitude,
        public float $maxLongitude,
    ) {
    }
}
