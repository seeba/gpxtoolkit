<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use DateTimeImmutable;
use GpxToolkit\Geometry\Scope;

/**
 * Statistics of tracks or routes. Distances in meters, durations in seconds,
 * speeds in meters per second. A result that cannot be computed from the data is null.
 */
final readonly class Statistics
{
    /**
     * @param int                    $pairCount                  pairs of consecutive points within lines
     * @param float|null             $elevationGainMeters        sum of climbs over pairs where both points have elevation
     * @param int                    $elevationPairCount         pairs where both points have elevation
     * @param DateTimeImmutable|null $startTime                  earliest point time
     * @param DateTimeImmutable|null $endTime                    latest point time
     * @param float|null             $durationSeconds            elapsed time between startTime and endTime, including pauses and gaps
     * @param float|null             $movingDurationSeconds      time of timed pairs, excluding rest periods and gaps between lines
     * @param float|null             $restDurationSeconds        time of detected rest periods
     * @param int                    $timedPairCount             pairs where both points have a time and the time increases
     * @param int                    $nonIncreasingTimePairCount pairs where both points have a time that does not increase; excluded from time-based results
     * @param float|null             $averageSpeedMetersPerSecond distance divided by elapsed duration
     * @param float|null             $movingSpeedMetersPerSecond distance of moving timed pairs divided by moving duration
     */
    public function __construct(
        public Scope $scope,
        public int $lineCount,
        public int $pointCount,
        public int $pairCount,
        public float $distanceMeters,
        public ?float $elevationGainMeters,
        public ?float $elevationLossMeters,
        public int $elevationPairCount,
        public ?float $minElevationMeters,
        public ?float $maxElevationMeters,
        public ?DateTimeImmutable $startTime,
        public ?DateTimeImmutable $endTime,
        public ?float $durationSeconds,
        public ?float $movingDurationSeconds,
        public ?float $restDurationSeconds,
        public int $timedPairCount,
        public int $nonIncreasingTimePairCount,
        public ?float $averageSpeedMetersPerSecond,
        public ?float $movingSpeedMetersPerSecond,
    ) {
    }

    /**
     * Share of point pairs with elevation on both ends, from 0 to 1; null without pairs.
     */
    public function elevationCompleteness(): ?float
    {
        return 0 === $this->pairCount ? null : $this->elevationPairCount / $this->pairCount;
    }

    public function averagePaceSecondsPerKilometer(): ?float
    {
        return self::pace($this->averageSpeedMetersPerSecond);
    }

    public function movingPaceSecondsPerKilometer(): ?float
    {
        return self::pace($this->movingSpeedMetersPerSecond);
    }

    private static function pace(?float $speedMetersPerSecond): ?float
    {
        return null === $speedMetersPerSecond || $speedMetersPerSecond <= 0 ? null : 1000 / $speedMetersPerSecond;
    }
}
