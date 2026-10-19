<?php

declare(strict_types=1);

namespace GpxToolkit\Analysis;

enum SteepSegmentType: string
{
    case Climb = 'climb';
    case Descent = 'descent';
}
