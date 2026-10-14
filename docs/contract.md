# Kontrakt GPXToolkit 1.x

Dokument opisuje wspierany zakres, publiczne API, semantykę wyników i błędy.
Elementy oznaczone **[etap N]** są częścią kontraktu 1.0, ale ich implementacja
jest jeszcze w toku zgodnie z planem wydania.

## Wspierany format

- Wyłącznie **GPX 1.1**. Korzeń `gpx` musi mieć atrybut `version="1.1"`.
- Elementy GPX muszą należeć do namespace `http://www.topografix.com/GPX/1/1`.
  Prefiks nie ma znaczenia (`<gpx xmlns="…">` i `<g:gpx xmlns:g="…">` są równoważne).
- **Tryb zgodności** (`ToolkitOptions::$allowMissingNamespace`, domyślnie wyłączony):
  dokument, którego korzeń nie ma żadnego namespace, jest czytany jak GPX 1.1.
  Wersja nadal musi wynosić `1.1`.
- GPX 1.0 (namespace `…/GPX/1/0`) i każdy inny namespace są odrzucane
  `ValidationException`, również w trybie zgodności.
- Atrybut `creator` jest wymagany przez XSD, ale biblioteka go nie wymaga;
  brak daje pusty string.
- Walidacja XSD nie jest wykonywana. Biblioteka waliduje model, nie kolejność
  elementów: nieznane elementy w namespace GPX są pomijane.

## Źródła wejścia

`Toolkit::parseFile()`, `parseString()` i `parseStream()` korzystają z tego
samego parsera i dają identyczny model dla tej samej treści. Rozszerzenie pliku
nie ma znaczenia. Strumień jest czytany od bieżącej pozycji do końca i nie jest
zamykany. Nazwa źródła (ścieżka lub `sourceName`) służy wyłącznie komunikatom błędów.

Polityka XML **[etap 2]**: bez dostępu do sieci (`LIBXML_NONET`), bez rozwijania
encji zewnętrznych i ładowania DTD; dokument z `DOCTYPE` jest odrzucany
`XmlParseException`. Konfigurowalne limity rozmiaru treści i liczby punktów.
Limit rozmiaru sprawdzany przed wczytaniem całej treści; limit punktów przed
budową modelu, ale po zbudowaniu drzewa XML — przed zużyciem pamięci chroni
wyłącznie limit rozmiaru.

## Rodzaje dokumentów

| Dokument | Wynik |
|---|---|
| Uszkodzony XML | `XmlParseException` |
| Poprawny XML, zły korzeń/wersja/namespace | `ValidationException` |
| `<gpx/>` bez zawartości | poprawny `Document` z pustymi listami |
| Same metadane, same waypointy, same trasy | poprawny `Document`; parser nie wymaga śladu |
| Ślad z segmentem pustym lub jednopunktowym | poprawny `Document`; geometria liniowa takiego segmentu jest niedostępna **[etap 3]** |

## Publiczne API

Gwarancja kompatybilności 1.x obejmuje:

- `GpxToolkit\Toolkit`, `GpxToolkit\ToolkitOptions`
- `GpxToolkit\Model\*`: `Document`, `Metadata`, `Person`, `Email`, `Link`,
  `Copyright`, `Bounds`, `Point`, `Route`, `Track`, `TrackSegment`,
  `Extensions`, `FixType`
- `GpxToolkit\Exception\*`: `GpxToolkitException` (interfejs), `ReadException`,
  `XmlParseException`, `ValidationException`
- `GpxToolkit\Middleware\Middleware` i dostarczone middleware
- `GpxToolkit\Analysis\*` i `GpxToolkit\Geometry\*`

Klasy oznaczone `@internal` (`GpxToolkit\Internal\*`) nie są objęte gwarancją.
Nowe opcjonalne parametry konstruktorów mogą być dodawane na końcu listy;
argumenty należy przekazywać nazwane, jeśli pomija się parametry opcjonalne.

### Model

- Wszystkie klasy modelu są `final readonly`. Zmiana danych odbywa się przez
  metody `with…()` zwracające nową instancję.
- `Document` zawiera wyłącznie dane źródłowe. Wyniki pochodne (statystyki,
  bounds, WKT) nie są przechowywane w modelu — liczą je usługi `Analysis`
  i `Geometry`, więc nie mogą być nieaktualne po transformacji.
- Waypointy, punkty tras i punkty śladów to ta sama klasa `Point` (`wptType`).
- Pola opcjonalne mają wartość `null`, gdy element nie występuje. Brak
  pomiaru nigdy nie jest zamieniany na `0`; zero w pliku jest poprawnym pomiarem.
- `<extensions>` jest zachowywane w surowej postaci w `Extensions` na poziomie
  dokumentu, metadanych, śladu, segmentu, trasy i punktu **[etap 2]**.

| Element GPX | Pole | Jednostka / typ |
|---|---|---|
| `@lat`, `@lon` | `latitude`, `longitude` | stopnie dziesiętne WGS 84; zakres −90…90 i −180…180 **[etap 2]** |
| `ele` | `elevation` | metry |
| `time` | `time` | `DateTimeImmutable` ze strefą z pliku; składnia `xsd:dateTime` **[etap 2]** |
| `magvar` | `magneticVariation` | stopnie |
| `geoidheight` | `geoidHeight` | metry |
| `cmt`, `desc`, `src`, `sym` | `comment`, `description`, `source`, `symbol` | tekst |
| `fix` | `fix` | `FixType` |
| `sat` | `satelliteCount` | liczba całkowita |
| `hdop`, `vdop`, `pdop` | `hdop`, `vdop`, `pdop` | liczba |
| `ageofdgpsdata` | `ageOfDgpsData` | sekundy |
| `dgpsid` | `dgpsId` | 0…1023 |
| `copyright/year` | `Copyright::$year` | rok |

### Middleware

`Middleware::handle(Document, callable $next): Document`. Middleware działa
po parsowaniu, w kolejności podanej w konstruktorze `Toolkit` lub
`withMiddleware()`. Nie modyfikuje dokumentu wejściowego (model jest
niemutowalny) — zwraca nowy albo przekazuje go dalej. Konfiguracja należy do
instancji `Toolkit`; instancje nie współdzielą stanu.

Biblioteka nie ma middleware działających przed parsowaniem: odczyt pliku,
stringa i strumienia jest wspólny, a przetwarzanie surowej treści należy do
kodu wywołującego.

## Błędy

Każdy wyjątek biblioteki implementuje `GpxToolkitException`.

| Wyjątek | Kiedy | Kontekst |
|---|---|---|
| `ReadException` (`RuntimeException`) | brak pliku, katalog zamiast pliku, błąd odczytu | `source` |
| `XmlParseException` (`RuntimeException`) | XML niepoprawny składniowo lub naruszający politykę XML | `source`, `xmlLine` |
| `ValidationException` (`InvalidArgumentException`) | poprawny XML niezgodny z kontraktem, niepoprawne dane modelu lub wejścia narzędzi | `source`, `elementPath` |

Komunikaty nie zawierają treści dokumentu. Biblioteka nie emituje ostrzeżeń PHP
i przywraca stan obsługi błędów libxml. Wyjątki PHP (`ValueError`, `Exception`
z parsera dat) nie wydostają się z parsera **[etap 2]**. Przekazanie do
`parseStream()` czegoś innego niż strumień to błąd programisty
(`InvalidArgumentException` spoza hierarchii biblioteki).

## Semantyka wyników [etap 3]

Jednostki: metry, sekundy, metry na sekundę; tempo w sekundach na kilometr.
Nazwy pól wyników zawierają jednostkę (`distanceMeters`, `durationSeconds`…).
Wynik niedostępny to `null`, nie `0` ani tekst.

- **Zakres:** obliczenia dotyczą śladów albo tras — jawnie wybranych, domyślnie
  śladów. Wynik zawiera użyty zakres. Dokument ze śladem i trasą nie jest
  sumowany.
- **Dystans:** suma odległości haversine (kula o promieniu 6 371 000 m) między
  kolejnymi punktami w obrębie segmentu. Przerwy między segmentami i śladami
  nie są dystansem.
- **Przewyższenia:** liczone tylko dla par sąsiednich punktów, które obie mają
  wysokość. Wynik zawiera liczbę par z pomiarem i informację o kompletności.
- **Czas:** kolejność punktów pochodzi z pliku i nie jest zmieniana. Czas
  całkowity to różnica między najpóźniejszym a najwcześniejszym czasem
  w zakresie. Czas ruchu sumuje tylko pary w obrębie segmentu z rosnącym czasem,
  z wyłączeniem wykrytych odpoczynków (parametry: próg prędkości, minimalny czas
  trwania). Pary z czasem malejącym lub równym są pomijane w obliczeniach
  czasowych i liczone w wyniku.
- **Profil wysokościowy:** dystans skumulowany w całym zakresie, z indeksem
  śladu i segmentu przy każdym punkcie; punkty bez wysokości mają `null`.
- **WKT:** kolejność `longitude latitude`, bez SRID, formatowanie niezależne od
  locale ze stałą precyzją. Ślad z wieloma segmentami jako `MULTILINESTRING`;
  segmenty z mniej niż dwoma punktami są pomijane, a brak linii daje `null`.

## Fixture'y referencyjne

Pliki w `tests/Fixtures/gpx/`.

| Fixture | Oczekiwane zachowanie |
|---|---|
| `track.gpx` | jeden ślad, jeden segment, pełne metadane |
| `full-point.gpx` | każde pole `wptType` zmapowane na `Point` |
| `route.gpx` | trasa bez czasów |
| `track-and-route.gpx` | ślad i trasa tego samego przebiegu; statystyki nie sumują zakresów **[etap 3]** |
| `waypoints-only.gpx`, `metadata-only.gpx`, `empty-document.gpx` | poprawny dokument bez śladu |
| `multi-track-multi-segment.gpx` | struktura śladów i segmentów zachowana, w tym pusty segment |
| `loop.gpx` | pętla: redukcja zachowuje kształt |
| `namespace-prefixed.gpx` | czytany jak domyślny namespace |
| `namespace-missing.gpx` | `ValidationException`; w trybie zgodności poprawny |
| `namespace-other.gpx`, `gpx-1.0.gpx` | `ValidationException` |
| `missing-time-elevation.gpx` | braki jako `null`; zero to pomiar **[etap 3]** |
| `invalid-coordinates.gpx` | `ValidationException` ze ścieżką elementu **[etap 2]** |
| `invalid-time.gpx`, `invalid-fix.gpx` | `ValidationException` ze ścieżką elementu **[etap 2]** |
| `malformed.gpx` | `XmlParseException` z numerem linii |
| `doctype-entities.gpx` | `XmlParseException`, bez odczytu plików i sieci **[etap 2]** |
| `extensions.gpx` | surowe extensions z namespace zachowane **[etap 2]** |

## Migracja z 0.x

| 0.x | 1.x |
|---|---|
| `GPXToolkit::configure()` + `GPXToolkit::parse($path)` | `(new Toolkit($options, $middleware))->parseFile($path)` |
| `FileHandlerRegistry`, `FileInterface`, middleware „before” | usunięte; `parseString()`, `parseStream()` |
| `InvalidArgumentException` | `GpxToolkitException` i podklasy |
| `Services\Model\Gpx\Gpx` | `Model\Document` |
| `WayPoint`, `RoutePoint`, `TrackPoint`, `Point` | `Model\Point` |
| `Segment`, `TrackSegment` | `Model\TrackSegment` |
| `Point::$magVar`, `$satelitesQuantity`, `$dgsid` | `magneticVariation`, `satelliteCount`, `dgpsId` |
| `Point::$ageOfGpsData` (`int`, czytane z błędnego elementu) | `ageOfDgpsData` (`float`, element `ageofdgpsdata`) |
| `Point::$wktPoint`, `Track::$wktLineString` | `Geometry\WktWriter` |
| `Metadata::$description` (czytane z błędnego elementu `description`) | `description` z elementu `desc` |
| `Metadata::$time` (`DateTime`), `setBounds()` | `DateTimeImmutable`; model niemutowalny |
| `Gpx::$statistics`, `PrepareStatisticsMiddleware` | `Analysis\StatisticsCalculator::calculate()` → `Analysis\Statistics` |
| `CreateBoundsIfNotExistMiddleware` | `Geometry\BoundsCalculator::calculate()` |
| `CreateWktMiddleware`, `GenerateWkt` | `Geometry\WktWriter` |
| `ReducePointsMiddleware`, `ReduceGPXPoints` | `Middleware\ReducePointsMiddleware`, `Geometry\PointReducer` |
| `StatisticCalculator::calculateDistance()` | `Geometry\Distance::haversineMeters()` |
| `StatisticCalculator::detectRestPeriods()`, `detectSteepSegments()` | `Analysis\RestPeriodDetector`, `Analysis\SteepSegmentDetector` |
| `StatisticCalculator::calculateAveragePace()` (tekst) | `Statistics::$averagePaceSecondsPerKilometer` + `Analysis\PaceFormatter` |
| `StatisticCalculator::calculateMovingPace()`, `StatisticType`, `$statisticsTypes` | usunięte; czas ruchu w statystykach **[etap 3]** |
