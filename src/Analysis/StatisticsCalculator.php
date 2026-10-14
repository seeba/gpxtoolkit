<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use DateTimeImmutable;
use GpxToolkit\Geometry\Distance;
use GpxToolkit\Model\Document;
use GpxToolkit\Model\Point;

final class StatisticsCalculator
{
    public function calculate(Document $document): Statistics
    {
        $distance = 0.0;
        $elevationGain = 0.0;
        $elevationLoss = 0.0;
        $pointCount = 0;
        $elevationProfile = [];
        $speedDistribution = ['0-5' => 0, '5-10' => 0, '10-15' => 0, '15-20' => 0, '20+' => 0];
        $firstTime = null;
        $lastTime = null;

        foreach ($document->tracks as $track) {
            foreach ($track->segments as $segment) {
                $points = $segment->points;
                $pointCount += count($points);

                usort($points, static fn (Point $a, Point $b): int => ($a->time?->getTimestamp() ?? PHP_INT_MAX)
                    <=> ($b->time?->getTimestamp() ?? PHP_INT_MAX));

                foreach ($points as $point) {
                    if (null !== $point->time) {
                        $firstTime = self::earlier($firstTime, $point->time);
                        $lastTime = self::later($lastTime, $point->time);
                    }
                }

                $previous = null;
                $segmentDistance = 0.0;
                foreach ($points as $point) {
                    if (null !== $previous) {
                        $pairDistance = Distance::haversineMeters($previous, $point);
                        $segmentDistance += $pairDistance;

                        $elevationDiff = ($point->elevation ?? 0.0) - ($previous->elevation ?? 0.0);
                        if ($elevationDiff > 0) {
                            $elevationGain += $elevationDiff;
                        } else {
                            $elevationLoss += abs($elevationDiff);
                        }

                        if (null !== $point->time && null !== $previous->time) {
                            $seconds = $point->time->getTimestamp() - $previous->time->getTimestamp();
                            if ($seconds > 0) {
                                ++$speedDistribution[self::speedBucket(($pairDistance / 1000) / ($seconds / 3600))];
                            }
                        }
                    }

                    $elevationProfile[] = ['distanceMeters' => $segmentDistance, 'elevationMeters' => $point->elevation ?? 0.0];
                    $previous = $point;
                }

                $distance += $segmentDistance;
            }
        }

        usort($elevationProfile, static fn (array $a, array $b): int => $a['distanceMeters'] <=> $b['distanceMeters']);

        $duration = null !== $firstTime && null !== $lastTime
            ? $lastTime->getTimestamp() - $firstTime->getTimestamp()
            : 0;
        $averageSpeed = $duration > 0 ? $distance / $duration : 0.0;

        return new Statistics(
            $distance,
            $elevationGain,
            $elevationLoss,
            $duration,
            $averageSpeed,
            $averageSpeed > 0 ? 1000 / $averageSpeed : null,
            $pointCount,
            $speedDistribution,
            $elevationProfile,
        );
    }

    private static function speedBucket(float $speedKmh): string
    {
        return match (true) {
            $speedKmh < 5 => '0-5',
            $speedKmh < 10 => '5-10',
            $speedKmh < 15 => '10-15',
            $speedKmh < 20 => '15-20',
            default => '20+',
        };
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
