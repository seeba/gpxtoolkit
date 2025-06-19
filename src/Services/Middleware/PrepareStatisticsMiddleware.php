<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Middleware;

use GpxToolkit\Services\Model\Additional\Statistics;
use GpxToolkit\Services\Model\Gpx\Gpx;
use GpxToolkit\Services\Utils\StatisticCalculator;
use InvalidArgumentException;

class PrepareStatisticsMiddleware implements MiddlewareInterface
{
    /**
     * @param Gpx $data
     */
    public function handle(mixed $data, callable $next): mixed
    {
        if (!$data instanceof Gpx) {
            throw new InvalidArgumentException('Data must be an instance of Gpx');
        }

        $statistics = StatisticCalculator::calculateGpxStatistics($data);

        $data->statistics = new Statistics(
            $statistics['totalDistance'],
            $statistics['totalElevationGain'],
            $statistics['totalElevationLoss'],
            $statistics['totalDuration'],
            $statistics['averageSpeed'],
            $statistics['averagePace'],
            $statistics['totalPoints'],
            $statistics['speedDistribution'],
            $statistics['elevationProfile']
        );

        return $next($data);
    }
}
