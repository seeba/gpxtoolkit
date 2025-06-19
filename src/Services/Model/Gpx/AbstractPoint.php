<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Gpx;

use DateTimeImmutable;

abstract class AbstractPoint
{
    protected function __construct(
        public float $latitude,
        public float $longitude,
        public float $elevation,
        public ?DateTimeImmutable $time = null,
    ) {
    }
}
