<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

use GpxToolkit\Model\Document;
use GpxToolkit\Model\Point;
use GpxToolkit\Model\Route;
use GpxToolkit\Model\Track;
use InvalidArgumentException;

/**
 * Writes WKT geometry in "longitude latitude" order (WGS 84), without SRID.
 *
 * Coordinates are rounded to a fixed number of decimal places, independent of locale,
 * with trailing zeros removed. Consecutive points with the same rounded coordinates are
 * written once; a line with fewer than two distinct points has no linear geometry.
 * Separate segments and routes are never joined into one line.
 */
final readonly class WktWriter
{
    public const int DEFAULT_PRECISION = 7;

    public function __construct(
        private int $precision = self::DEFAULT_PRECISION,
    ) {
        if ($precision < 0 || $precision > 15) {
            throw new InvalidArgumentException('Precision must be between 0 and 15 decimal places.');
        }
    }

    public function point(Point $point): string
    {
        return 'POINT('.$this->coordinates($point).')';
    }

    /**
     * @param list<Point> $points
     *
     * @return string|null null when the points do not form a line
     */
    public function lineString(array $points): ?string
    {
        $coordinates = $this->lineCoordinates($points);

        return null === $coordinates ? null : 'LINESTRING('.$coordinates.')';
    }

    /**
     * Parts that do not form a line are skipped.
     *
     * @param list<list<Point>> $parts
     *
     * @return string|null null when no part forms a line
     */
    public function multiLineString(array $parts): ?string
    {
        $lines = $this->partCoordinates($parts);

        return [] === $lines ? null : 'MULTILINESTRING(('.implode('), (', $lines).'))';
    }

    /**
     * LINESTRING when exactly one segment forms a line, MULTILINESTRING when more do.
     */
    public function track(Track $track): ?string
    {
        $lines = $this->partCoordinates(array_map(
            static fn (Line $line): array => $line->points,
            Lines::fromTrack($track),
        ));

        return match (count($lines)) {
            0 => null,
            1 => 'LINESTRING('.$lines[0].')',
            default => 'MULTILINESTRING(('.implode('), (', $lines).'))',
        };
    }

    public function route(Route $route): ?string
    {
        return $this->lineString($route->points);
    }

    /**
     * All lines of the scope as one MULTILINESTRING, even when there is only one.
     */
    public function document(Document $document, Scope $scope = Scope::Tracks): ?string
    {
        return $this->multiLineString(array_map(
            static fn (Line $line): array => $line->points,
            Lines::fromDocument($document, $scope),
        ));
    }

    /**
     * @param list<list<Point>> $parts
     *
     * @return list<string>
     */
    private function partCoordinates(array $parts): array
    {
        $lines = [];
        foreach ($parts as $points) {
            $coordinates = $this->lineCoordinates($points);
            if (null !== $coordinates) {
                $lines[] = $coordinates;
            }
        }

        return $lines;
    }

    /**
     * @param list<Point> $points
     */
    private function lineCoordinates(array $points): ?string
    {
        $coordinates = [];
        $last = null;
        foreach ($points as $point) {
            $current = $this->coordinates($point);
            if ($current !== $last) {
                $coordinates[] = $current;
                $last = $current;
            }
        }

        return count($coordinates) < 2 ? null : implode(', ', $coordinates);
    }

    private function coordinates(Point $point): string
    {
        return $this->number($point->longitude).' '.$this->number($point->latitude);
    }

    private function number(float $value): string
    {
        $formatted = sprintf('%.'.$this->precision.'F', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return '-0' === $formatted ? '0' : $formatted;
    }
}
