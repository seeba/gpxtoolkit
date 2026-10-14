<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

/**
 * Aggregated statistics of the document tracks.
 */
final readonly class Statistics
{
    /**
     * @param array<string, int>                                             $speedDistributionKmh number of point pairs per speed bucket in km/h
     * @param list<array{distanceMeters: float, elevationMeters: float}>      $elevationProfile
     */
    public function __construct(
        public float $distanceMeters,
        public float $elevationGainMeters,
        public float $elevationLossMeters,
        public int $durationSeconds,
        public float $averageSpeedMetersPerSecond,
        public ?float $averagePaceSecondsPerKilometer,
        public int $pointCount,
        public array $speedDistributionKmh,
        public array $elevationProfile,
    ) {
    }
}
