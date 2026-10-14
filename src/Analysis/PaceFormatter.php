<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

/**
 * Presentation helper: formats pace as "m:ss" per kilometer.
 */
final class PaceFormatter
{
    public function format(?float $secondsPerKilometer): ?string
    {
        if (null === $secondsPerKilometer || !is_finite($secondsPerKilometer) || $secondsPerKilometer <= 0) {
            return null;
        }

        $seconds = (int) round($secondsPerKilometer);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
