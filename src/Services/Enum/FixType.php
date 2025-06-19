<?php

declare(strict_types=1);

namespace GpxToolkit\Services\Enum;

enum FixType : string
{
    case NONE = 'none';
    case TYPE_2D = '2d';
    case TYPE_3D = '3d';
    case TYPE_DGPS = 'dgps';
    case TYPE_PPS = 'pps';
}
