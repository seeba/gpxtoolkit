<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Internal\Assert;

/**
 * GPX 1.1 boundsType, in decimal degrees (WGS 84).
 *
 * @throws ValidationException when a coordinate is out of range or minimum exceeds maximum
 */
final readonly class Bounds
{
    public function __construct(
        public float $minLatitude,
        public float $minLongitude,
        public float $maxLatitude,
        public float $maxLongitude,
    ) {
        Assert::latitude($minLatitude, 'minimum latitude');
        Assert::latitude($maxLatitude, 'maximum latitude');
        Assert::longitude($minLongitude, 'minimum longitude');
        Assert::longitude($maxLongitude, 'maximum longitude');
        if ($minLatitude > $maxLatitude || $minLongitude > $maxLongitude) {
            throw new ValidationException('Bounds minimum must not exceed maximum; areas crossing the 180th meridian are not supported.');
        }
    }
}
