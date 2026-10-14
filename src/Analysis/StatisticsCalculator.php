<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use DateTimeImmutable;
use GpxToolkit\Geometry\Distance;
use GpxToolkit\Geometry\Line;
use GpxToolkit\Geometry\Lines;
use GpxToolkit\Geometry\Scope;
use GpxToolkit\Internal\Time;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Route;
use GpxToolkit\Model\Track;

/**
 * Computes statistics line by line (track segment or route) and aggregates them.
 * Point order is taken from the document and never changed.
 */
final readonly class StatisticsCalculator
{
    public function __construct(
        private RestPeriodDetector $restPeriodDetector = new RestPeriodDetector(),
    ) {
    }

    public function calculate(Document $document, Scope $scope = Scope::Tracks): Statistics
    {
        return $this->calculateLines(Lines::fromDocument($document, $scope), $scope);
    }

    public function calculateTrack(Track $track): Statistics
    {
        return $this->calculateLines(Lines::fromTrack($track), Scope::Tracks);
    }

    public function calculateRoute(Route $route): Statistics
    {
        return $this->calculateLines([Lines::fromRoute($route)], Scope::Routes);
    }

    /**
     * @param list<Line> $lines
     */
    public function calculateLines(array $lines, Scope $scope): Statistics
    {
        $pointCount = 0;
        $pairCount = 0;
        $distance = 0.0;
        $gain = 0.0;
        $loss = 0.0;
        $elevationPairCount = 0;
        $minElevation = null;
        $maxElevation = null;
        $startTime = null;
        $endTime = null;
        $timedPairCount = 0;
        $timedDuration = 0.0;
        $timedDistance = 0.0;
        $nonIncreasingTimePairCount = 0;
        $restDuration = 0.0;
        $restDistance = 0.0;

        foreach ($lines as $line) {
            $previous = null;
            foreach ($line->points as $point) {
                ++$pointCount;

                if (null !== $point->elevation) {
                    $minElevation = null === $minElevation ? $point->elevation : min($minElevation, $point->elevation);
                    $maxElevation = null === $maxElevation ? $point->elevation : max($maxElevation, $point->elevation);
                }
                if (null !== $point->time) {
                    $startTime = self::earlier($startTime, $point->time);
                    $endTime = self::later($endTime, $point->time);
                }

                if (null !== $previous) {
                    ++$pairCount;
                    $pairDistance = Distance::haversineMeters($previous, $point);
                    $distance += $pairDistance;

                    if (null !== $point->elevation && null !== $previous->elevation) {
                        ++$elevationPairCount;
                        $difference = $point->elevation - $previous->elevation;
                        if ($difference > 0) {
                            $gain += $difference;
                        } else {
                            $loss -= $difference;
                        }
                    }

                    if (null !== $point->time && null !== $previous->time) {
                        $seconds = Time::seconds($point->time) - Time::seconds($previous->time);
                        if ($seconds > 0) {
                            ++$timedPairCount;
                            $timedDuration += $seconds;
                            $timedDistance += $pairDistance;
                        } else {
                            ++$nonIncreasingTimePairCount;
                        }
                    }
                }

                $previous = $point;
            }

            foreach ($this->restPeriodDetector->detect($line->points) as $period) {
                $restDuration += $period->durationSeconds;
                $restDistance += $period->distanceMeters;
            }
        }

        $duration = null !== $startTime && null !== $endTime
            ? Time::seconds($endTime) - Time::seconds($startTime)
            : null;
        $movingDuration = $timedPairCount > 0 ? $timedDuration - $restDuration : null;

        return new Statistics(
            $scope,
            count($lines),
            $pointCount,
            $pairCount,
            $distance,
            $elevationPairCount > 0 ? $gain : null,
            $elevationPairCount > 0 ? $loss : null,
            $elevationPairCount,
            $minElevation,
            $maxElevation,
            $startTime,
            $endTime,
            $duration,
            $movingDuration,
            $timedPairCount > 0 ? $restDuration : null,
            $timedPairCount,
            $nonIncreasingTimePairCount,
            null !== $duration && $duration > 0 ? $distance / $duration : null,
            null !== $movingDuration && $movingDuration > 0 ? ($timedDistance - $restDistance) / $movingDuration : null,
        );
    }

    private static function earlier(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return null === $current || $candidate < $current ? $candidate : $current;
    }

    private static function later(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return null === $current || $candidate > $current ? $candidate : $current;
    }
}
