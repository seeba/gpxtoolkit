<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Model\Additional;

class Statistics
{
    public function __construct(
        public ?float $totalDistance = null,
        public ?float $totalElevationGain = null,
        public ?float $totalElevationLoss = null,
        public ?int $totalDuration = null,
        public ?float $averageSpeed = null,
        public ?string $averagePace = null,
        public ?int $totalPoints = null,
        public ?array $speedDistribution = null,
        public ?array $elevationProfile = null
    ) {
    }
}
