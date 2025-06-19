<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Utils;

enum StatisticType: string
{
    case TOTAL_DISTANCE = 'total_distance';
    case TOTAL_ELEVATION_GAIN = 'total_elevation_gain';
    case TOTAL_ELEVATION_LOSS = 'total_elevation_loss';
    case TOTAL_DURATION = 'total_duration';
    case AVERAGE_SPEED = 'average_speed';
    case AVERAGE_PACE = 'average_pace';
    case TOTAL_POINTS = 'total_points';
    case MAX_SPEED = 'max_speed';
    case SPEED_DISTRIBUTION = 'speed_distribution';
}
