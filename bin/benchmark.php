<?php

declare(strict_types=1);

/*
 * Measures parsing and analysis of deterministic generated tracks.
 *
 * Usage: php bin/benchmark.php [point count ...]
 * Every count runs in a separate process, so peak memory is measured independently.
 */

use GpxToolkit\Analysis\StatisticsCalculator;
use GpxToolkit\Geometry\PointReducer;
use GpxToolkit\Geometry\WktWriter;
use GpxToolkit\Middleware\ReducePointsMiddleware;
use GpxToolkit\Model\Document;
use GpxToolkit\Toolkit;
use GpxToolkit\ToolkitOptions;

require dirname(__DIR__).'/vendor/autoload.php';

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];
$counts = array_map('intval', array_slice($arguments, 1));
if ([] === $counts) {
    $counts = [10_000, 100_000, 500_000, 1_000_000];
}

if (count($counts) > 1) {
    printf("%10s %10s %10s %10s %10s %10s %10s\n", 'points', 'MiB', 'parse s', 'stats s', 'wkt s', 'reduce s', 'peak MiB');
    foreach ($counts as $count) {
        passthru(escapeshellarg(PHP_BINARY).' -d memory_limit='.escapeshellarg((string) ini_get('memory_limit')).' '.escapeshellarg(__FILE__).' '.$count, $status);
        if (0 !== $status) {
            exit($status);
        }
    }

    exit(0);
}

$count = $counts[0];
$xml = generate($count);
$bytes = strlen($xml);
$toolkit = new Toolkit(new ToolkitOptions(maxBytes: null, maxPoints: null));

$start = hrtime(true);
$document = $toolkit->parseString($xml);
$parseSeconds = elapsed($start);
unset($xml);

$start = hrtime(true);
(new StatisticsCalculator())->calculate($document);
$statisticsSeconds = elapsed($start);

$start = hrtime(true);
(new WktWriter())->document($document);
$wktSeconds = elapsed($start);

$start = hrtime(true);
(new ReducePointsMiddleware(new PointReducer()))->handle($document, static fn (Document $document): Document => $document);
$reduceSeconds = elapsed($start);

printf(
    "%10d %10.1f %10.2f %10.2f %10.2f %10.2f %10.1f\n",
    $count,
    $bytes / 1024 / 1024,
    $parseSeconds,
    $statisticsSeconds,
    $wktSeconds,
    $reduceSeconds,
    memory_get_peak_usage(true) / 1024 / 1024,
);

function elapsed(int|float $start): float
{
    return (hrtime(true) - $start) / 1e9;
}

/**
 * A winding track with elevation and time, one point per second; the same for every run.
 */
function generate(int $count): string
{
    $points = [];
    $start = 1_780_000_000;
    for ($i = 0; $i < $count; ++$i) {
        $angle = $i / 200;
        $points[] = sprintf(
            '<trkpt lat="%.7F" lon="%.7F"><ele>%.1F</ele><time>%s</time></trkpt>',
            50.0 + $i * 0.000_02 + sin($angle) * 0.001,
            19.0 + cos($angle) * 0.001 + ($i % 7) * 0.000_001,
            300.0 + sin($i / 1000) * 50,
            gmdate('Y-m-d\TH:i:s\Z', $start + $i),
        );
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1" creator="benchmark"><trk><trkseg>'
        .implode("\n", $points)
        .'</trkseg></trk></gpx>';
}
