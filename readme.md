# GPXToolkit

Biblioteka PHP do odczytu plików **GPX 1.1** i analizy ich zawartości:
metadanych, waypointów, tras i śladów. Liczy statystyki, profil wysokościowy,
odpoczynki, strome odcinki, bounds i WKT oraz upraszcza ślady.

Pakiet: `seeba/gpxtoolkit`. Namespace: `GpxToolkit`. Bez zależności od frameworków.

Pełny opis wspieranego zakresu, semantyki wyników, błędów i polityki
kompatybilności: [docs/contract.md](docs/contract.md). Zmiany:
[CHANGELOG.md](CHANGELOG.md).

## Wymagania i instalacja

PHP 8.4 lub 8.5 z rozszerzeniami `dom`, `libxml` i `simplexml`.

```bash
composer require seeba/gpxtoolkit:^1.0
```

## Odczyt

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use GpxToolkit\Exception\GpxToolkitException;
use GpxToolkit\Toolkit;

$toolkit = new Toolkit();

try {
    $document = $toolkit->parseFile($argv[1] ?? __DIR__.'/trasa.gpx');
} catch (GpxToolkitException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

echo 'Twórca: '.$document->creator.PHP_EOL;
echo 'Nazwa: '.($document->metadata?->name ?? 'brak').PHP_EOL;

foreach ($document->tracks as $track) {
    echo 'Ślad: '.($track->name ?? 'bez nazwy').PHP_EOL;
    foreach ($track->segments as $segment) {
        foreach ($segment->points as $point) {
            printf("%.6f %.6f %s\n", $point->latitude, $point->longitude, $point->elevation ?? '-');
        }
    }
}
```

Te same wyniki dają `parseString($xml)` i `parseStream($resource)`.
Rozszerzenie pliku nie ma znaczenia, więc plik tymczasowy uploadu można
czytać bezpośrednio.

Przykładowy `trasa.gpx`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="example" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata><name>Przykładowy spacer</name></metadata>
  <trk>
    <name>Pierwszy ślad</name>
    <trkseg>
      <trkpt lat="50.0000" lon="19.0000"><ele>200</ele><time>2026-10-13T10:00:00Z</time></trkpt>
      <trkpt lat="50.0010" lon="19.0010"><ele>210</ele><time>2026-10-13T10:02:00Z</time></trkpt>
      <trkpt lat="50.0020" lon="19.0020"><ele>205</ele><time>2026-10-13T10:04:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
```

### Konfiguracja

Każda instancja `Toolkit` ma własną konfigurację; instancje nie współdzielą stanu.

```php
use GpxToolkit\Toolkit;
use GpxToolkit\ToolkitOptions;

$toolkit = new Toolkit(new ToolkitOptions(
    allowMissingNamespace: false, // true: dokument bez namespace czytany jak GPX 1.1
    maxBytes: 32 * 1024 * 1024,   // null wyłącza limit
    maxPoints: 300_000,           // null wyłącza limit
));
```

Model jest budowany w pamięci (ok. 1,2 KiB na punkt); pomiary są w kontrakcie.

## Model

Wszystkie klasy w `GpxToolkit\Model` są `final readonly`; zmiany przez
metody `with…()` zwracają nowe instancje.

| Dostęp | Zawartość |
| --- | --- |
| `$document->version`, `$document->creator` | atrybuty `gpx` |
| `$document->metadata` | `?Metadata`: nazwa, opis, autor, licencja, linki, czas, słowa kluczowe, bounds z pliku |
| `$document->waypoints` | `list<Point>` z `wpt` |
| `$document->routes` | `list<Route>`, punkty w `$route->points` |
| `$document->tracks` | `list<Track>`, segmenty w `$track->segments`, punkty w `$segment->points` |
| `Point` | wszystkie pola `wptType`; brak pomiaru to `null`, nigdy `0` |
| `->extensions` | `?Extensions` z surowymi fragmentami XML (dokument, metadane, ślad, segment, trasa, punkt) |

## Analiza

Obliczenia dotyczą śladów (`Scope::Tracks`, domyślnie) albo tras
(`Scope::Routes`) — nigdy obu naraz. Przerwy między segmentami nie są łączone.
Jednostki: metry, sekundy, metry na sekundę.

```php
use GpxToolkit\Analysis\ElevationProfileBuilder;
use GpxToolkit\Analysis\PaceFormatter;
use GpxToolkit\Analysis\StatisticsCalculator;
use GpxToolkit\Geometry\BoundsCalculator;
use GpxToolkit\Geometry\Scope;
use GpxToolkit\Geometry\WktWriter;

$statistics = (new StatisticsCalculator())->calculate($document, Scope::Tracks);

printf("Dystans: %.2f km\n", $statistics->distanceMeters / 1000);
printf("Podejścia: %s m\n", $statistics->elevationGainMeters ?? 'brak danych');
printf("Czas całkowity: %s s, czas ruchu: %s s\n", $statistics->durationSeconds ?? '-', $statistics->movingDurationSeconds ?? '-');
echo 'Tempo: '.((new PaceFormatter())->format($statistics->movingPaceSecondsPerKilometer()) ?? '-').' min/km'.PHP_EOL;

$profile = (new ElevationProfileBuilder())->build($document);   // list<ProfilePoint>
$bounds = (new BoundsCalculator())->calculate($document);       // ?Bounds
$wkt = (new WktWriter())->document($document);                  // ?string, MULTILINESTRING
```

Narzędzia działające na punktach jednej linii (segmentu albo trasy):

```php
use GpxToolkit\Analysis\RestPeriodDetector;
use GpxToolkit\Analysis\SteepSegmentDetector;
use GpxToolkit\Geometry\Lines;

foreach (Lines::fromDocument($document) as $line) {
    $rests = (new RestPeriodDetector(speedThresholdMetersPerSecond: 0.15, minDurationSeconds: 120))->detect($line->points);
    $steep = (new SteepSegmentDetector(gradeThresholdPercent: 10.0, minDistanceMeters: 50.0))->detect($line->points);
}
```

## Upraszczanie śladów i middleware

Middleware działa po parsowaniu, w podanej kolejności, i zwraca nowy dokument.
Wyniki analizy liczone z dokumentu po middleware opisują już uproszczony ślad.

```php
use GpxToolkit\Geometry\PointReducer;
use GpxToolkit\Middleware\ReducePointsMiddleware;
use GpxToolkit\Toolkit;

$toolkit = (new Toolkit())->withMiddleware(
    new ReducePointsMiddleware(new PointReducer(toleranceMeters: 2.0)),
);
```

Własne middleware implementuje `GpxToolkit\Middleware\Middleware`:

```php
use GpxToolkit\Middleware\Middleware;
use GpxToolkit\Model\Document;

final class DropWaypoints implements Middleware
{
    public function handle(Document $document, callable $next): Document
    {
        return $next(new Document(
            $document->version,
            $document->creator,
            $document->metadata,
            routes: $document->routes,
            tracks: $document->tracks,
            extensions: $document->extensions,
        ));
    }
}
```

## Błędy

Każdy wyjątek biblioteki implementuje `GpxToolkit\Exception\GpxToolkitException`:
`ReadException` (plik), `XmlParseException` (XML, z numerem linii),
`ValidationException` (dane niezgodne z GPX 1.1, ze ścieżką elementu)
i `LimitExceededException`. DOCTYPE i encje zewnętrzne są odrzucane.

## Migracja z 0.x

Tabela odpowiedników jest w [docs/contract.md](docs/contract.md#migracja-z-0x).
Najważniejsze zmiany: instancja `Toolkit` zamiast statycznej fasady, wyłącznie
GPX 1.1 z namespace, statystyki liczone przez `StatisticsCalculator` zamiast
pola modelu, tolerancja redukcji w metrach, segmenty nie są łączone w WKT
i statystykach.

## Rozwój

```bash
composer install
composer ci          # composer validate, PHP-CS-Fixer, PHPStan, PHPUnit
composer benchmark   # czas i pamięć dla wygenerowanych śladów
```

## Licencja

MIT, zob. [LICENSE](LICENSE).
