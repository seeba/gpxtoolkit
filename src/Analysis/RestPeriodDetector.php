<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

use DateTimeImmutable;
use GpxToolkit\Exception\ValidationException;
use GpxToolkit\Geometry\Distance;
use GpxToolkit\Model\Point;

/**
 * Finds periods in which the speed stays below a threshold for at least the minimum duration.
 * Requires every point to have a time.
 */
final readonly class RestPeriodDetector
{
    public function __construct(
        private float $speedThresholdKmh = 0.5,
        private int $minDurationSeconds = 120,
    ) {
    }

    /**
     * @param list<Point> $points
     *
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable, durationSeconds: int}>
     */
    public function detect(array $points): array
    {
        $periods = [];
        $restStart = null;
        $previous = null;
        $previousTime = null;

        foreach ($points as $index => $point) {
            if (null === $point->time) {
                throw new ValidationException(sprintf('Rest period detection requires time of every point, point %d has none.', $index));
            }

            if (null !== $previous) {
                $seconds = $point->time->getTimestamp() - $previousTime->getTimestamp();
                $speedKmh = $seconds > 0 ? (Distance::haversineMeters($previous, $point) / 1000) / ($seconds / 3600) : 0.0;

                if ($speedKmh < $this->speedThresholdKmh) {
                    $restStart ??= $previousTime;
                } elseif (null !== $restStart) {
                    $this->close($periods, $restStart, $previousTime);
                    $restStart = null;
                }
            }

            $previous = $point;
            $previousTime = $point->time;
        }

        if (null !== $restStart && null !== $previousTime) {
            $this->close($periods, $restStart, $previousTime);
        }

        return $periods;
    }

    /**
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable, durationSeconds: int}> $periods
     */
    private function close(array &$periods, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $duration = $end->getTimestamp() - $start->getTimestamp();
        if ($duration >= $this->minDurationSeconds) {
            $periods[] = ['start' => $start, 'end' => $end, 'durationSeconds' => $duration];
        }
    }
}
