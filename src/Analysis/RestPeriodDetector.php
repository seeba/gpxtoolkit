<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use GpxToolkit\Geometry\Distance;
use GpxToolkit\Internal\Time;
use GpxToolkit\Model\Point;
use InvalidArgumentException;

/**
 * Finds rest periods: runs of consecutive point pairs whose speed stays below the threshold
 * for at least the minimum duration.
 *
 * Only pairs where both points have a time and the time increases are considered.
 * A pair without time or with non-increasing time ends the current run.
 */
final readonly class RestPeriodDetector
{
    public const float DEFAULT_SPEED_THRESHOLD_METERS_PER_SECOND = 0.15;
    public const int DEFAULT_MIN_DURATION_SECONDS = 120;

    public function __construct(
        public float $speedThresholdMetersPerSecond = self::DEFAULT_SPEED_THRESHOLD_METERS_PER_SECOND,
        public int $minDurationSeconds = self::DEFAULT_MIN_DURATION_SECONDS,
    ) {
        if (!is_finite($speedThresholdMetersPerSecond) || $speedThresholdMetersPerSecond <= 0) {
            throw new InvalidArgumentException('Speed threshold must be a positive number.');
        }
        if ($minDurationSeconds < 0) {
            throw new InvalidArgumentException('Minimum rest duration must not be negative.');
        }
    }

    /**
     * @param list<Point> $points points of one continuous line
     *
     * @return list<RestPeriod>
     */
    public function detect(array $points): array
    {
        $periods = [];
        $runStart = null;
        $runDuration = 0.0;
        $runDistance = 0.0;

        foreach ($points as $index => $point) {
            if (0 === $index) {
                continue;
            }

            $previous = $points[$index - 1];
            $seconds = null !== $previous->time && null !== $point->time
                ? Time::seconds($point->time) - Time::seconds($previous->time)
                : null;

            if (null !== $seconds && $seconds > 0) {
                $distance = Distance::haversineMeters($previous, $point);
                if ($distance / $seconds < $this->speedThresholdMetersPerSecond) {
                    $runStart ??= $index - 1;
                    $runDuration += $seconds;
                    $runDistance += $distance;

                    continue;
                }
            }

            $this->close($periods, $points, $runStart, $index - 1, $runDuration, $runDistance);
            $runStart = null;
            $runDuration = 0.0;
            $runDistance = 0.0;
        }

        $this->close($periods, $points, $runStart, count($points) - 1, $runDuration, $runDistance);

        return $periods;
    }

    /**
     * @param list<RestPeriod> $periods
     * @param list<Point>      $points
     */
    private function close(array &$periods, array $points, ?int $start, int $end, float $duration, float $distance): void
    {
        if (null === $start || $duration < $this->minDurationSeconds) {
            return;
        }

        $startTime = $points[$start]->time;
        $endTime = $points[$end]->time;
        if (null === $startTime || null === $endTime) {
            return;
        }

        $periods[] = new RestPeriod($startTime, $endTime, $duration, $distance, $start, $end);
    }
}
