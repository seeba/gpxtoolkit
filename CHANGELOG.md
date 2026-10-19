# Changelog

Format zgodny z [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/),
wersjonowanie semantyczne. Polityka kompatybilności 1.x: [docs/contract.md](docs/contract.md#polityka-kompatybilności-1x).

## [1.0.0] — 2026-10-19

Pierwsze stabilne wydanie. Zmiana niekompatybilna z 0.x w całym publicznym API;
odpowiedniki starych klas i metod: [docs/contract.md](docs/contract.md#migracja-z-0x).

### Added

- `Toolkit` z konfiguracją instancji (`ToolkitOptions`) i middleware;
  `parseFile()`, `parseString()`, `parseStream()` z jednym parserem.
- Model `GpxToolkit\Model` pokrywający GPX 1.1 (`wptType` w całości, metadane,
  autor, licencja, linki) z surowymi `Extensions` na każdym poziomie.
- Hierarchia wyjątków `GpxToolkitException` ze źródłem, ścieżką elementu i linią XML.
- Limity `maxBytes` (32 MiB) i `maxPoints` (300 000).
- `Analysis`: `StatisticsCalculator` (czas całkowity i czas ruchu, kompletność
  wysokości, tempo), `ElevationProfileBuilder`, `RestPeriodDetector`,
  `SteepSegmentDetector`, `PaceFormatter`.
- `Geometry`: `Scope`, `Lines`, `BoundsCalculator`, `WktWriter`
  (`MULTILINESTRING`, stała precyzja), `PointReducer`, `Distance`.
- CI dla PHP 8.4 i 8.5, PHP-CS-Fixer, PHPStan na poziomie max, benchmark.

### Changed

- Wyłącznie GPX 1.1 z namespace; brak namespace tylko w trybie zgodności.
- Model niemutowalny; wyniki pochodne nie są przechowywane w modelu.
- Statystyki nie łączą segmentów, nie sortują punktów i nie zamieniają braków na `0`.
- Redukcja punktów z tolerancją w metrach zamiast stopni.

### Removed

- Statyczna fasada `GPXToolkit`, `FileHandlerRegistry`, middleware „before”.
- `Services\*`, `StatisticType`, `$statisticsTypes`, `speedDistribution`.

### Security

- Odrzucanie `DOCTYPE`, brak rozwijania encji i dostępu do sieci w libxml.
