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
- Walidacja XSD nie jest wykonywana. Biblioteka waliduje wartości i wymagane
  atrybuty, nie kolejność elementów: nieznane elementy są pomijane, a przy
  powtórzonym elemencie pojedynczym (np. dwóch `<name>`) liczy się pierwszy.

### Składnia wartości

- Liczby dziesiętne: `xsd:decimal` (`[+-]?cyfry[.cyfry]`), białe znaki na
  brzegach są ignorowane. Notacja wykładnicza, `NaN` i `INF` są odrzucane.
- Liczby całkowite: `[+-]?cyfry` w zakresie `int` PHP.
- Czas: `xsd:dateTime` (`YYYY-MM-DDThh:mm:ss[.ułamek][Z|±hh:mm]`) z poprawną
  datą kalendarzową, godziną 00–23 i sekundą 00–59. Czas bez strefy jest
  traktowany jako UTC (specyfikacja GPX). Ułamek sekundy jest obcinany do
  mikrosekund. Ogólny parser dat PHP nie jest używany.
- `copyright/year`: `xsd:gYear`, przechowywany jako `int`.
- `fix`: jedna z wartości `none`, `2d`, `3d`, `dgps`, `pps`.
- Teksty (`name`, `desc` itd.) są przechowywane bez zmian, łącznie z białymi znakami.

### Wymagane atrybuty i zakresy

| Element | Wymagane | Zakres |
|---|---|---|
| `wpt`, `rtept`, `trkpt` | `lat`, `lon` | lat −90…90, lon −180…180 (180 włącznie, mimo `maxExclusive` w XSD) |
| `bounds` | `minlat`, `minlon`, `maxlat`, `maxlon` | jak wyżej; minimum ≤ maksimum |
| `link` | `href` | — |
| `email` | `id`, `domain` | — |
| `copyright` | `author` | — |
| `magvar` | — | 0 ≤ x < 360 |
| `sat`, `number` | — | ≥ 0 |
| `dgpsid` | — | 0…1023 |

Obszar przekraczający południk 180° nie jest wspierany: `Bounds` wymaga
`minLongitude ≤ maxLongitude`, więc metadane z takim obszarem są odrzucane.

Te same niezmienniki sprawdzają publiczne konstruktory `Point`, `Bounds`,
`Track` i `Route` (`ValidationException` bez `source` i `elementPath`).

## Źródła wejścia

`Toolkit::parseFile()`, `parseString()` i `parseStream()` korzystają z tego
samego parsera i dają identyczny model dla tej samej treści. Rozszerzenie pliku
nie ma znaczenia. Strumień jest czytany od bieżącej pozycji do końca i nie jest
zamykany. Nazwa źródła (ścieżka lub `sourceName`) służy wyłącznie komunikatom błędów.

### Polityka XML

- Bez dostępu do sieci (`LIBXML_NONET`), bez rozwijania encji (`LIBXML_NOENT`)
  i ładowania DTD (`LIBXML_DTDLOAD`), bez zniesienia limitów libxml
  (`LIBXML_PARSEHUGE`).
- Dokument z deklaracją `DOCTYPE` jest odrzucany `XmlParseException` —
  sprawdzane w prologu przed parsowaniem oraz w drzewie po parsowaniu
  (obejmuje kodowania inne niż UTF-8).
- Stan obsługi błędów libxml jest przywracany także przy wyjątkach.

### Limity (`ToolkitOptions`)

| Opcja | Domyślnie | Sprawdzana |
|---|---|---|
| `maxBytes` | 32 MiB | przed odczytem: rozmiar pliku, długość stringa; ze strumienia czytane jest najwyżej `maxBytes + 1` bajtów |
| `maxPoints` | 1 000 000 | łączna liczba `wpt`, `rtept`, `trkpt` przed budową modelu |

`null` wyłącza limit. Przekroczenie daje `LimitExceededException`. Limit
punktów jest sprawdzany po zbudowaniu drzewa XML, więc przed zużyciem pamięci
przez parser chroni wyłącznie `maxBytes`. Wartości domyślne zostaną
zweryfikowane pomiarami przed wydaniem 1.0.

## Rodzaje dokumentów

| Dokument | Wynik |
|---|---|
| Uszkodzony XML | `XmlParseException` |
| Poprawny XML, zły korzeń/wersja/namespace | `ValidationException` |
| `<gpx/>` bez zawartości | poprawny `Document` z pustymi listami |
| Same metadane, same waypointy, same trasy | poprawny `Document`; parser nie wymaga śladu |
| Ślad z segmentem pustym lub jednopunktowym | poprawny `Document`; segment nie ma geometrii liniowej |

## Publiczne API

Gwarancja kompatybilności 1.x obejmuje:

- `GpxToolkit\Toolkit`, `GpxToolkit\ToolkitOptions`
- `GpxToolkit\Model\*`: `Document`, `Metadata`, `Person`, `Email`, `Link`,
  `Copyright`, `Bounds`, `Point`, `Route`, `Track`, `TrackSegment`,
  `Extensions`, `FixType`
- `GpxToolkit\Exception\*`: `GpxToolkitException` (interfejs), `ReadException`,
  `XmlParseException`, `ValidationException`, `LimitExceededException`
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
  `Metadata::$bounds` to wartość z pliku (dane źródłowe), a nie wynik obliczeń;
  transformacje jej nie przeliczają. Aktualne bounds daje `BoundsCalculator`.
- Waypointy, punkty tras i punkty śladów to ta sama klasa `Point` (`wptType`).
- Pola opcjonalne mają wartość `null`, gdy element nie występuje. Brak
  pomiaru nigdy nie jest zamieniany na `0`; zero w pliku jest poprawnym pomiarem.
- `<extensions>` jest zachowywane bez interpretacji w `Extensions` na poziomie
  dokumentu, metadanych, śladu, segmentu, trasy i punktu. `Extensions::$elements`
  to lista elementów potomnych, każdy jako samodzielny fragment XML deklarujący
  wszystkie używane namespace'y (np. `<gpxtpx:hr xmlns:gpxtpx="…">120</gpxtpx:hr>`).
  Tekst poza elementami potomnymi jest pomijany. Brak `<extensions>` to `null`,
  pusty element to pusta lista.

| Element GPX | Pole | Jednostka / typ |
|---|---|---|
| `@lat`, `@lon` | `latitude`, `longitude` | stopnie dziesiętne WGS 84 |
| `ele` | `elevation` | metry |
| `time` | `time` | `DateTimeImmutable` ze strefą z pliku (UTC, gdy brak) |
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

Transformacja zachowuje wszystko, czego nie zmienia: `with…()` kopiują
pozostałe pola, w tym metadane i extensions na każdym poziomie.

Dostarczone middleware:

- `ReducePointsMiddleware(PointReducer $reducer = new PointReducer())` —
  upraszcza każdy segment śladu (`PointReducer::reduce()`); trasy, waypointy,
  metadane i extensions pozostają bez zmian.

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
| `LimitExceededException` (`RuntimeException`) | przekroczony `maxBytes` lub `maxPoints` | `source`, `limit` |

`elementPath` ma postać XPath z indeksami od 1, np.
`/gpx/trk[1]/trkseg[2]/trkpt[15]/ele` dla błędnej wartości elementu,
`/gpx/wpt[3]/@lat` dla błędnej składni atrybutu lub `/gpx/wpt[3]` dla
naruszenia zakresu albo braku wymaganego atrybutu.

Komunikaty nie zawierają treści dokumentu. Biblioteka nie emituje ostrzeżeń PHP
i przywraca stan obsługi błędów libxml. Wyjątki PHP (`ValueError`, `Exception`
z parsera dat) nie wydostają się z parsera. Przekazanie do
`parseStream()` czegoś innego niż strumień to błąd programisty
(`InvalidArgumentException` spoza hierarchii biblioteki).

## Semantyka wyników

Jednostki: metry, sekundy (`float`, z mikrosekundami), metry na sekundę; tempo
w sekundach na kilometr. Nazwy pól wyników zawierają jednostkę. Wynik, którego
nie da się policzyć z danych, to `null` — nigdy `0` ani tekst.

- **Zakres (`Geometry\Scope`):** obliczenia dotyczą śladów (`Tracks`, domyślnie)
  albo tras (`Routes`). Wynik zawiera użyty zakres. Dokument ze śladem i trasą
  nie jest sumowany.
- **Linie (`Geometry\Lines`):** ślad dzieli się na segmenty, trasa to jedna linia.
  Obliczenia przebiegają linia po linii, w kolejności dokumentu; punkty nigdy nie
  są sortowane. Przerwy między segmentami, śladami i trasami nie są łączone.
- **Dystans:** suma odległości haversine (kula 6 371 000 m) między kolejnymi
  punktami w obrębie linii.
- **Przewyższenia:** tylko dla par sąsiednich punktów z wysokością na obu końcach;
  `null`, gdy takich par nie ma. `elevationPairCount` i `elevationCompleteness()`
  (udział takich par, 0…1) informują o kompletności. Zero jest pomiarem.
- **Czas całkowity (`durationSeconds`):** różnica między najpóźniejszym
  a najwcześniejszym czasem w zakresie, łącznie z przerwami i nocami między
  śladami; `null` bez punktów z czasem.
- **Pary czasowe:** para w obrębie linii z czasem na obu końcach i rosnącym
  czasem (`timedPairCount`). Pary z czasem malejącym lub równym są pomijane
  w wynikach czasowych i liczone w `nonIncreasingTimePairCount`. Para, w której
  brakuje czasu, nie jest parą czasową.
- **Odpoczynek (`Analysis\RestPeriodDetector`):** ciąg kolejnych par czasowych
  o prędkości poniżej progu (domyślnie 0,15 m/s) trwający co najmniej minimalny
  czas (domyślnie 120 s). Para bez czasu lub z czasem nierosnącym przerywa ciąg.
- **Czas ruchu (`movingDurationSeconds`):** suma czasu par czasowych minus czas
  odpoczynków; `null` bez par czasowych. Przerwy między liniami nie są ruchem.
- **Prędkości:** `averageSpeedMetersPerSecond` = dystans / czas całkowity;
  `movingSpeedMetersPerSecond` = dystans par czasowych poza odpoczynkami / czas
  ruchu. Tempo (`averagePaceSecondsPerKilometer()`,
  `movingPaceSecondsPerKilometer()`) to 1000 / prędkość; formatowanie tekstowe
  wyłącznie przez `Analysis\PaceFormatter`.
- **Profil wysokościowy (`Analysis\ElevationProfileBuilder`):** każdy punkt
  zakresu w kolejności dokumentu, ze skumulowanym dystansem (przerwy między
  liniami nie zwiększają dystansu), wysokością lub `null` oraz indeksami ścieżki,
  segmentu i punktu.
- **Strome odcinki (`Analysis\SteepSegmentDetector`):** ciąg kolejnych par
  w obrębie linii o nachyleniu co najmniej progu (domyślnie 10 %) w tym samym
  kierunku i łącznym dystansie co najmniej minimum (domyślnie 50 m). Para bez
  wysokości na którymś końcu albo o zerowym dystansie przerywa ciąg; zmiana
  kierunku zaczyna nowy. Wynik: lista `SteepSegment` (typ `SteepSegmentType::Climb`
  lub `Descent`, punkty i ich indeksy w linii, dystans, zmiana wysokości,
  średnie nachylenie — ujemne dla zjazdów).
- **Warunki wejścia narzędzi:** detektory (`RestPeriodDetector`,
  `SteepSegmentDetector`) i `PointReducer` przyjmują punkty jednej ciągłej linii
  (`Line::$points`, segment albo trasa), tolerują brakujące pomiary zgodnie
  z opisem powyżej i zwracają indeksy w obrębie tej linii. Niepoprawna
  konfiguracja konstruktora to `InvalidArgumentException` (błąd programisty).
- **Redukcja (`Geometry\PointReducer`):** Ramer–Douglas–Peucker z tolerancją
  w metrach (domyślnie 1 m). Odległość punktu od odcinka między zachowanymi
  końcami liczona w lokalnej projekcji równoodległościowej linii (długość
  geograficzna skalowana przez `cos` średniej szerokości, przejście przez
  południk 180° obsługiwane). Odległość od odcinka, a nie od prostej, więc pętle
  i trasy tam-i-z-powrotem zachowują kształt. Pierwszy i ostatni punkt są zawsze
  zachowane, kolejność nie zmienia się, linie krótsze niż 3 punkty są zwracane
  bez zmian; tolerancja 0 usuwa tylko punkty leżące dokładnie na odcinku.
- **Bounds (`Geometry\BoundsCalculator`):** wszystkie punkty zakresu; `null`
  bez punktów.
- **WKT (`Geometry\WktWriter`):** `longitude latitude`, bez SRID (WGS 84 jest
  kontraktem, nie częścią tekstu), stała precyzja (domyślnie 7 miejsc, ok. 1 cm)
  niezależna od locale, bez końcowych zer. Kolejne punkty o tych samych
  zaokrąglonych współrzędnych są zapisywane raz; linia z mniej niż dwoma
  różnymi punktami nie ma geometrii. `track()` daje `LINESTRING` dla jednego
  segmentu z geometrią i `MULTILINESTRING` dla wielu, `route()` — `LINESTRING`,
  `document()` — zawsze `MULTILINESTRING` z linii zakresu; brak geometrii to `null`.

## Fixture'y referencyjne

Pliki w `tests/Fixtures/gpx/`.

| Fixture | Oczekiwane zachowanie |
|---|---|
| `track.gpx` | jeden ślad, jeden segment, pełne metadane |
| `full-point.gpx` | każde pole `wptType` zmapowane na `Point` |
| `route.gpx` | trasa bez czasów |
| `track-and-route.gpx` | ślad i trasa tego samego przebiegu; statystyki nie sumują zakresów |
| `waypoints-only.gpx`, `metadata-only.gpx`, `empty-document.gpx` | poprawny dokument bez śladu |
| `multi-track-multi-segment.gpx` | struktura śladów i segmentów zachowana, w tym pusty segment |
| `loop.gpx` | pętla: redukcja zachowuje kształt |
| `namespace-prefixed.gpx` | czytany jak domyślny namespace |
| `namespace-missing.gpx` | `ValidationException`; w trybie zgodności poprawny |
| `namespace-other.gpx`, `gpx-1.0.gpx` | `ValidationException` |
| `missing-time-elevation.gpx` | braki jako `null`, zero to pomiar; przewyższenia `null`, brak czasu ruchu |
| `invalid-coordinates.gpx` | `ValidationException` ze ścieżką elementu |
| `invalid-time.gpx`, `invalid-fix.gpx` | `ValidationException` ze ścieżką elementu |
| `malformed.gpx` | `XmlParseException` z numerem linii |
| `doctype-entities.gpx` | `XmlParseException`, bez odczytu plików i sieci |
| `extensions.gpx` | surowe extensions z namespace zachowane |

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
| `Point::$wktPoint`, `Track::$wktLineString` (segmenty sklejone) | `Geometry\WktWriter::point()`, `track()` (segmenty osobno) |
| `Metadata::$description` (czytane z błędnego elementu `description`) | `description` z elementu `desc` |
| `Metadata::$time` (`DateTime`), `setBounds()` | `DateTimeImmutable`; model niemutowalny |
| `Gpx::$statistics`, `PrepareStatisticsMiddleware` | `Analysis\StatisticsCalculator::calculate($document, $scope)` → `Analysis\Statistics` |
| `Statistics::$totalDistance`, `$totalElevationGain`, `$totalElevationLoss`, `$totalDuration`, `$averageSpeed`, `$totalPoints` | `distanceMeters`, `elevationGainMeters`, `elevationLossMeters`, `durationSeconds`, `averageSpeedMetersPerSecond`, `pointCount` |
| `Statistics::$speedDistribution` | usunięte |
| `Statistics::$elevationProfile` (sortowany, dystans od zera w każdym segmencie) | `Analysis\ElevationProfileBuilder` (skumulowany, w kolejności dokumentu) |
| `CreateBoundsIfNotExistMiddleware` | `Geometry\BoundsCalculator::calculate()` |
| `CreateWktMiddleware`, `GenerateWkt` | `Geometry\WktWriter` |
| `ReducePointsMiddleware`, `ReduceGPXPoints` (tolerancja w stopniach) | `Middleware\ReducePointsMiddleware`, `Geometry\PointReducer` (`toleranceMeters`, domyślnie 1 m) |
| `StatisticCalculator::calculateDistance()` | `Geometry\Distance::haversineMeters()` |
| `StatisticCalculator::detectRestPeriods()` (km/h, wymaga czasu wszystkich punktów) | `Analysis\RestPeriodDetector` (m/s, toleruje braki) → `RestPeriod` |
| `StatisticCalculator::detectSteepSegments()` (tablice, wymaga wysokości wszystkich punktów, minimum dystansu wyłącznie) | `Analysis\SteepSegmentDetector` → `SteepSegment` (toleruje braki, minimum włącznie) |
| `StatisticCalculator::calculateAveragePace()` (tekst) | `Statistics::averagePaceSecondsPerKilometer()` + `Analysis\PaceFormatter` |
| `StatisticCalculator::calculateMovingPace()` | `Statistics::movingPaceSecondsPerKilometer()` |
| `StatisticType`, `$statisticsTypes` | usunięte |
