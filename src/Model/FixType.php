<?php

declare(strict_types=1);

namespace GpxToolkit\Model;

/**
 * GPX 1.1 fixType.
 */
enum FixType: string
{
    case None = 'none';
    case TwoD = '2d';
    case ThreeD = '3d';
    case Dgps = 'dgps';
    case Pps = 'pps';
}
