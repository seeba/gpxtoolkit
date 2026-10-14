<?php

declare(strict_types=1);

namespace GpxToolkit\Geometry;

/**
 * Part of the document used by calculations. Tracks and routes are never combined,
 * because a document often contains both for the same path.
 */
enum Scope: string
{
    /** Recorded track segments (trk/trkseg/trkpt). */
    case Tracks = 'tracks';

    /** Planned routes (rte/rtept). */
    case Routes = 'routes';
}
