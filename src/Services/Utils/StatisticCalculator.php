<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Utils;

use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Model\Gpx\Point;
use GpxToolkit\Services\Model\Gpx\Segment;
use GpxToolkit\Services\Model\Gpx\Track;

class StatisticCalculator
{
    private const EARTH_RADIUS = 6371000;
    private static array $speedDistribution = [
        '0-5' => 0,    // 0-5 km/h
        '5-10' => 0,   // 5-10 km/h
        '10-15' => 0,  // 10-15 km/h
        '15-20' => 0,  // 15-20 km/h
        '20+' => 0     // powyżej 20 km/h
    ];

    /**
     * Oblicza wszystkie statystyki dla ekspedycji
     */
    public static function calculateGpxStatistics(Gpx $gpx, array $statisticsTypes = []): array
    {
        /**
         * @var Track[] $tracks
         */
        $tracks = $gpx->tracks;

        $totalDistance = 0;
        $totalElevationGain = 0;
        $totalElevationLoss = 0;
        $totalDuration = 0;
        $allPoints = [];
        $elevationProfile = [];
        $totalPoints = 0;

        $firstPointTime = null;
        $lastPointTime = null;

        foreach ($tracks as $track) {
            /**
             * @var Segment $segment
             */
            foreach ($track->segments as $segment) {
                /**
                 * @var Point[] $points
                 */
                $points = $segment->points;
                $totalPoints += count($points);

                // Sortowanie punktów według czasu
                usort($points, function (Point $a, Point $b) {
                    return $a->time <=> $b->time;
                });

                // Wyznaczenie pierwszego i ostatniego punktu całej ekspedycji
                if ($points && (!$firstPointTime || $points[0]->time < $firstPointTime)) {
                    $firstPointTime = $points[0]->time;
                }

                if ($points && (!$lastPointTime || end($points)->time > $lastPointTime)) {
                    $lastPointTime = end($points)->time;
                }

                // Obliczanie statystyk dla segmentu
                $segmentStats = self::calculateSegmentStatistics($points);

                $totalDistance += $segmentStats['distance'];
                $totalElevationGain += $segmentStats['elevationGain'];
                $totalElevationLoss += $segmentStats['elevationLoss'];

                // Uzupełnianie rozkładu prędkości
                foreach ($segmentStats['speeds'] as $speed) {
                    if ($speed < 5) {
                        self::$speedDistribution['0-5']++;
                    } elseif ($speed < 10) {
                        self::$speedDistribution['5-10']++;
                    } elseif ($speed < 15) {
                        self::$speedDistribution['10-15']++;
                    } elseif ($speed < 20) {
                        self::$speedDistribution['15-20']++;
                    } else {
                        self::$speedDistribution['20+']++;
                    }
                }

                // Uzupełnianie profilu wysokościowego
                foreach ($segmentStats['elevationPoints'] as $point) {
                    $elevationProfile[] = $point;
                }

                $allPoints = array_merge($allPoints, $points);
            }
        }

        // Sortowanie profilu wysokościowego po dystansie
        usort($elevationProfile, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Obliczanie całkowitego czasu trwania
        if ($firstPointTime && $lastPointTime) {
            $totalDuration = $lastPointTime->getTimestamp() - $firstPointTime->getTimestamp();
        }

        // Obliczanie średniej prędkości
        $averageSpeed = $totalDuration > 0 ? ($totalDistance) / ($totalDuration) : 0;

        return [
            'totalDistance' => $totalDistance,
            'totalElevationGain' => $totalElevationGain,
            'totalElevationLoss' => $totalElevationLoss,
            'totalDuration' => $totalDuration,
            'averageSpeed' => $averageSpeed,
            'averagePace' => self::calculateAveragePace($averageSpeed),
            'totalPoints' => $totalPoints,
            'speedDistribution' => self::$speedDistribution,
            'elevationProfile' => $elevationProfile
        ];
    }

    /**
     * Oblicza statystyki dla pojedynczego segmentu
     * @param Point[] $points
     * @return array{
     *      distance: float,
     *      elevationGain: float,
     *      elevationLoss: float,
     *      speeds: float[],
     *      elevationPoints: array<int, array{distance: float, elevation: float}>
     *  }
     */
    private static function calculateSegmentStatistics(array $points): array
    {
        $distance = 0;
        $elevationGain = 0;
        $elevationLoss = 0;
        $speeds = [];
        $elevationPoints = [];

        $prevPoint = null;

        foreach ($points as $point) {
            if ($prevPoint) {
                // Obliczanie dystansu między punktami
                $pointDistance = self::calculateDistance(
                    $prevPoint,
                    $point
                );

                $distance += $pointDistance;

                // Obliczanie różnicy wysokości
                $elevationDiff = $point->elevation - $prevPoint->elevation;
                if ($elevationDiff > 0) {
                    $elevationGain += $elevationDiff;
                } else {
                    $elevationLoss += abs($elevationDiff);
                }

                // Obliczanie prędkości między punktami
                $timeDiff = $point->time->getTimestamp() - $prevPoint->time->getTimestamp();
                if ($timeDiff > 0) {
                    // Prędkość w km/h
                    $speed = ($pointDistance / 1000) / ($timeDiff / 3600);
                    $speeds[] = $speed;
                }

                // Dodawanie punktu do profilu wysokościowego
                $elevationPoints[] = [
                    'distance' => $distance,
                    'elevation' => $point->elevation
                ];
            } else {
                // Pierwszy punkt profilu wysokościowego
                $elevationPoints[] = [
                    'distance' => 0,
                    'elevation' => $point->elevation
                ];
            }

            $prevPoint = $point;
        }

        return [
            'distance' => $distance,
            'elevationGain' => $elevationGain,
            'elevationLoss' => $elevationLoss,
            'speeds' => $speeds,
            'elevationPoints' => $elevationPoints
        ];
    }

    public static function calculateDistance(Point $firstPoint, Point $secondPoint): float
    {
        $dLat = deg2rad($secondPoint->latitude - $firstPoint->latitude);
        $dLon = deg2rad($secondPoint->longitude - $firstPoint->longitude);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($firstPoint->latitude)) * cos(deg2rad($secondPoint->latitude)) *
            sin($dLon / 2) * sin($dLon / 2);

        return 2 * self::EARTH_RADIUS * atan2(sqrt($a), sqrt(1 - $a));// dystans w metrach
    }

    /**
     * @param Point[] $points
     */
    public static function detectRestPeriods(
        array $points,
        float $speedThreshold = 0.5,
        int $minDuration = 120
    ): array {
        $restPeriods = [];
        $restStart = null;
        $prevPoint = null;

        foreach ($points as $point) {
            if ($prevPoint) {
                $timeDiff = $point->time->getTimestamp() - $prevPoint->time->getTimestamp();
                $distance = self::calculateDistance(
                    $prevPoint,
                    $point
                );

                // Prędkość w km/h
                $speed = $timeDiff > 0 ? ($distance / 1000) / ($timeDiff / 3600) : 0;

                // Jeśli prędkość poniżej progu, to potencjalny odpoczynek
                if ($speed < $speedThreshold) {
                    if ($restStart === null) {
                        $restStart = $prevPoint->time;
                    }
                } else {
                    // Jeśli wcześniej był odpoczynek, sprawdź, czy był wystarczająco długi
                    if ($restStart !== null) {
                        $restDuration = $prevPoint->time->getTimestamp() - $restStart->getTimestamp();
                        if ($restDuration >= $minDuration) {
                            $restPeriods[] = [
                                'start' => $restStart,
                                'end' => $prevPoint->time,
                                'duration' => $restDuration
                            ];
                        }
                        $restStart = null;
                    }
                }
            }

            $prevPoint = $point;
        }

        // Sprawdź, czy ostatni okres odpoczynku się zakończył
        if ($restStart !== null && $prevPoint !== null) {
            $restDuration = $prevPoint->time->getTimestamp() - $restStart->getTimestamp();
            if ($restDuration >= $minDuration) {
                $restPeriods[] = [
                    'start' => $restStart,
                    'end' => $prevPoint->time,
                    'duration' => $restDuration
                ];
            }
        }

        return $restPeriods;
    }

    /**
     * Oblicza tempo poruszania się (min/km) z wyłączeniem okresów odpoczynku
     */
    public static function calculateMovingPace(float $totalDistance, int $totalDuration, array $restPeriods): float
    {
        $restDuration = 0;
        foreach ($restPeriods as $period) {
            $restDuration += $period['duration'];
        }

        $movingTime = $totalDuration - $restDuration;
        if ($movingTime <= 0 || $totalDistance <= 0) {
            return 0;
        }

        // Tempo w min/km
        return ($movingTime / 60) / ($totalDistance / 1000);
    }

    /**
     * Wykrywa segmenty o znaczącym podejściu lub zejściu
     * @param Point[] $points
     */
    public function detectSteepSegments(array $points, float $gradeThreshold = 10): array
    {
        $steepSegments = [];
        $currentSegment = null;
        $prevPoint = null;
        $segmentDistance = 0;
        $segmentElevation = 0;

        foreach ($points as $point) {
            if ($prevPoint) {
                $distance = $this->calculateDistance(
                    $prevPoint,
                    $point
                );

                $elevationDiff = $point->elevation - $prevPoint->elevation;
                $grade = $distance > 0 ? ($elevationDiff / $distance) * 100 : 0;

                // Sprawdź, czy nachylenie przekracza próg
                if (abs($grade) >= $gradeThreshold) {
                    $type = $grade > 0 ? 'climb' : 'descent';

                    // Jeśli nie mamy aktualnego segmentu lub zmienił się typ
                    if ($currentSegment === null || $currentSegment['type'] !== $type) {
                        if ($currentSegment !== null && $segmentDistance > 50) {
                            $currentSegment['distance'] = $segmentDistance;
                            $currentSegment['elevationChange'] = $segmentElevation;
                            $currentSegment['grade'] = ($segmentElevation / $segmentDistance) * 100;
                            $currentSegment['end'] = $prevPoint->time;
                            $steepSegments[] = $currentSegment;
                        }

                        $currentSegment = [
                            'type' => $type,
                            'start' => $prevPoint->time,
                            'startPosition' => [
                                'lat' => $prevPoint->latitude,
                                'lon' => $prevPoint->longitude,
                                'ele' => $prevPoint->elevation
                            ]
                        ];

                        $segmentDistance = $distance;
                        $segmentElevation = $elevationDiff;
                    } else {
                        $segmentDistance += $distance;
                        $segmentElevation += $elevationDiff;
                    }
                } elseif ($currentSegment !== null && $segmentDistance > 50) {
                    $currentSegment['distance'] = $segmentDistance;
                    $currentSegment['elevationChange'] = $segmentElevation;
                    $currentSegment['grade'] = ($segmentElevation / $segmentDistance) * 100;
                    $currentSegment['end'] = $prevPoint->time;
                    $currentSegment['endPosition'] = [
                        'lat' => $point->latitude,
                        'lon' => $point->longitude,
                        'ele' => $point->elevation
                    ];
                    $steepSegments[] = $currentSegment;
                    $currentSegment = null;
                }
            }

            $prevPoint = $point;
        }

        // Zamknij ostatni segment, jeśli istnieje
        if ($currentSegment !== null && $segmentDistance > 50) {
            $currentSegment['distance'] = $segmentDistance;
            $currentSegment['elevationChange'] = $segmentElevation;
            $currentSegment['grade'] = ($segmentElevation / $segmentDistance) * 100;
            $currentSegment['end'] = $prevPoint->time;
            $currentSegment['endPosition'] = [
                'lat' => $prevPoint->latitude,
                'lon' => $prevPoint->longitude,
                'ele' => $prevPoint->elevation
            ];
            $steepSegments[] = $currentSegment;
        }

        return $steepSegments;
    }

    public static function calculateAveragePace(float $averageSpeed): string
    {
        {
        if ($averageSpeed <= 0) {
            return "Niepoprawna prędkość";
        }

            // Oblicz czas potrzebny na przebiegnięcie 1 km
            $czas_sekundy = 1000 / $averageSpeed;

            // Konwersja na minuty i sekundy
            $minuty = floor($czas_sekundy / 60);
            $sekundy = round($czas_sekundy % 60);

            // Formatowanie wyniku w postaci "min:sek"
            return sprintf("%d:%02d min/km", $minuty, $sekundy);
        }
    }
}
