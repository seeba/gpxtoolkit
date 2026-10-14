<?php

declare(strict_types=1);

namespace GpxToolkit\Internal;

use GpxToolkit\Exception\ValidationException;

/**
 * Model invariants shared by the reader and the public model constructors.
 *
 * @internal
 */
final class Assert
{
    public static function latitude(float $value, string $field = 'latitude'): void
    {
        self::finite($value, $field);
        if ($value < -90.0 || $value > 90.0) {
            throw new ValidationException(sprintf('%s must be between -90 and 90, %s given.', ucfirst($field), self::format($value)));
        }
    }

    public static function longitude(float $value, string $field = 'longitude'): void
    {
        self::finite($value, $field);
        if ($value < -180.0 || $value > 180.0) {
            throw new ValidationException(sprintf('%s must be between -180 and 180, %s given.', ucfirst($field), self::format($value)));
        }
    }

    public static function finite(?float $value, string $field): void
    {
        if (null !== $value && !is_finite($value)) {
            throw new ValidationException(sprintf('%s must be a finite number.', ucfirst($field)));
        }
    }

    public static function nonNegative(int|float|null $value, string $field): void
    {
        if (null !== $value && $value < 0) {
            throw new ValidationException(sprintf('%s must not be negative, %s given.', ucfirst($field), self::format($value)));
        }
    }

    public static function range(int|float|null $value, int|float $min, int|float $maxInclusive, string $field): void
    {
        if (null !== $value && ($value < $min || $value > $maxInclusive)) {
            throw new ValidationException(sprintf(
                '%s must be between %s and %s, %s given.',
                ucfirst($field),
                self::format($min),
                self::format($maxInclusive),
                self::format($value),
            ));
        }
    }

    private static function format(int|float $value): string
    {
        return is_float($value) ? rtrim(rtrim(sprintf('%.8F', $value), '0'), '.') : (string) $value;
    }
}
