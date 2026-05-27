#!/usr/bin/env php
<?php

/**
 * bin/geocode-facilities.php
 *
 * Populate facility coordinates so they can be plotted on the Sample Referral
 * Network map (Admin -> Monitoring). Two modes:
 *
 *   A. --csv=<file>  Import coordinates from a CSV (no external API). Best when
 *                    you already have lat/long (e.g. the Facilities-page export
 *                    with coordinates filled in). Columns are detected by header
 *                    name, so both the full export and a minimal
 *                    "Name, Code, Lat, Long" file work. Rows are matched to
 *                    facilities by Code first, then by exact Name.
 *
 *   B. (default)     Geocode via OpenStreetMap Nominatim (see strategy below).
 *
 * Strategy for the Nominatim mode (country-agnostic — not just Rwanda):
 *   1. Try a real coordinate from OpenStreetMap Nominatim using
 *      "<facility>, <district>, <province>, <country>".
 *   2. On a miss, fall back to the district centroid (also geocoded via
 *      Nominatim, cached so it costs ~1 call per district), then to the
 *      province centroid. A deterministic phyllotaxis ("sunflower") offset is
 *      applied so facilities sharing a centroid fan out instead of stacking.
 *   3. Persist into facility_details.latitude / facility_details.longitude.
 *
 * Idempotent & resumable: facilities that already have coordinates are skipped
 * unless --redo is given, so the script can be re-run to fill new facilities.
 *
 * Respects the Nominatim usage policy (valid User-Agent, <= 1 request/second).
 *
 * Usage:
 *   # Import from CSV (matches by Code, then Name)
 *   php bin/geocode-facilities.php --csv=facilities.csv [--spread] [--dry-run]
 *
 *   # Geocode via Nominatim
 *   php bin/geocode-facilities.php [--country="Rwanda"] [--limit=N] [--redo]
 *                                 [--sleep=1.1] [--dry-run] [--quiet] [--all]
 *
 *   --csv       Import coordinates from this CSV file instead of calling the API.
 *   --spread    (CSV mode) Fan out facilities that share an identical coordinate
 *               with a deterministic sunflower offset so they don't stack — handy
 *               for AI/LLM-filled data where many rows got the same district point.
 *   --country   Country name appended to every lookup. Disambiguates province /
 *               district names worldwide. If omitted, it is inferred from the
 *               instance's configured country (global_config.vl_form).
 *   --redo      Re-geocode facilities that already have coordinates.
 *   --limit     Process at most N facilities (handy for a quick test run).
 *   --sleep     Seconds between Nominatim calls (default 1.1, policy minimum 1).
 *   --all       Include facilities that have never recorded data.
 *   --dry-run   Show what would happen; write nothing.
 *   --quiet     Suppress per-facility output.
 */

require_once __DIR__ . "/../bootstrap.php";

use App\Services\TestsService;
use App\Services\DatabaseService;
use App\Utilities\NominatimGeocoderUtility;
use App\Registries\ContainerRegistry;

if (PHP_SAPI !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

/**
 * Distinct facility ids that have ever recorded data — i.e. appear as a
 * referring facility or a testing lab in any active test form.
 *
 * @return int[]
 */
function collectReferredFacilityIds(DatabaseService $db): array
{
    $ids = [];
    foreach (TestsService::getActiveTests() as $testType) {
        try {
            $table = TestsService::getTestTableName($testType);
        } catch (\Throwable) {
            continue;
        }
        foreach (['facility_id', 'lab_id'] as $col) {
            $rows = $db->rawQuery("SELECT DISTINCT $col AS id FROM $table WHERE $col IS NOT NULL") ?: [];
            foreach ($rows as $r) {
                $id = (int) $r['id'];
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }
    }
    return array_keys($ids);
}

/**
 * Import facility coordinates from a CSV (e.g. the Facilities-page export, with
 * lat/long filled in separately). Columns are detected by header name
 * (case-insensitive), so both the full export and a minimal "Name, Code, Lat,
 * Long" file work:
 *   - code : "Facility Code" | "Code"
 *   - name : "Facility Name" | "Name"   (fallback when code is blank / unmatched)
 *   - lat  : "Latitude" | "Lat"
 *   - lon  : "Longitude" | "Long" | "Lng" | "Lon"
 *
 * Each row is matched to facility_details by facility_code first, then by exact
 * (case-insensitive) facility name. When $spread is true, facilities that end up
 * sharing identical coordinates are fanned out with a deterministic sunflower
 * offset so they don't stack on one point.
 */
function importCoordinatesFromCsv(DatabaseService $db, string $path, bool $dryRun, bool $quiet, bool $spread): void
{
    if (!is_file($path) || !is_readable($path)) {
        exit("CSV file not found or not readable: $path\n");
    }
    $handle = fopen($path, 'r');
    if ($handle === false) {
        exit("Could not open CSV: $path\n");
    }

    $header = fgetcsv($handle);
    if (!is_array($header)) {
        fclose($handle);
        exit("CSV appears to be empty: $path\n");
    }
    $cols = csvDetectColumns($header);
    [$byCode, $byName] = csvFacilityLookups($db);

    $updates = [];   // facility_id => [lat, lon]
    $stats = ['rows' => 0, 'byCode' => 0, 'byName' => 0, 'noMatch' => 0, 'badCoord' => 0];

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
            continue; // blank line
        }
        $stats['rows']++;
        csvProcessRow($row, $cols, $byCode, $byName, $updates, $stats);
    }
    fclose($handle);

    $spreadCount = $spread ? spreadDuplicateCoordinates($updates) : 0;

    if (!$dryRun) {
        foreach ($updates as $fid => [$lat, $lon]) {
            $db->where('facility_id', $fid);
            $db->update('facility_details', ['latitude' => (string) $lat, 'longitude' => (string) $lon]);
        }
    }

    if (!$quiet) {
        csvPrintSummary($stats, count($updates), $spread ? $spreadCount : null, $dryRun);
    }
}

/**
 * Print the CSV import summary.
 *
 * @param array<string,int> $stats
 */
function csvPrintSummary(array $stats, int $updated, ?int $spreadCount, bool $dryRun): void
{
    echo "\n--------------------------------------------------\n";
    echo $dryRun ? "DRY RUN — no rows written.\n" : "Done.\n";
    echo "  CSV rows read         : {$stats['rows']}\n";
    echo "  Matched by code       : {$stats['byCode']}\n";
    echo "  Matched by name       : {$stats['byName']}\n";
    echo "  Facilities updated    : {$updated}\n";
    if ($spreadCount !== null) {
        echo "  Spread (shared point) : {$spreadCount}\n";
    }
    echo "  Unmatched (skipped)   : {$stats['noMatch']}\n";
    echo "  Invalid coordinates   : {$stats['badCoord']}\n";
    echo "--------------------------------------------------\n";
}

/**
 * Detect the code/name/lat/lon column indexes from a CSV header row.
 *
 * @param array<int, string|null> $header
 * @return array{code: ?int, name: ?int, lat: int, lon: int}
 */
function csvDetectColumns(array $header): array
{
    // Strip a UTF-8 BOM from the first header cell if present.
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));

    $find = static function (array $aliases) use ($header): ?int {
        foreach ($header as $i => $name) {
            if (in_array(strtolower(trim((string) $name)), $aliases, true)) {
                return $i;
            }
        }
        return null;
    };

    $cols = [
        'code' => $find(['facility code', 'code']),
        'name' => $find(['facility name', 'name']),
        'lat' => $find(['latitude', 'lat']),
        'lon' => $find(['longitude', 'long', 'lng', 'lon']),
    ];

    if ($cols['lat'] === null || $cols['lon'] === null || ($cols['code'] === null && $cols['name'] === null)) {
        exit("CSV needs Latitude and Longitude columns plus a Code and/or Name column.\n");
    }
    return $cols;
}

/**
 * Build lowercased code -> id and name -> id lookups from facility_details.
 *
 * @return array{0: array<string,int>, 1: array<string,int>}
 */
function csvFacilityLookups(DatabaseService $db): array
{
    $byCode = $byName = [];
    foreach ($db->rawQuery("SELECT facility_id, facility_code, facility_name FROM facility_details") ?: [] as $r) {
        $code = strtolower(trim((string) ($r['facility_code'] ?? '')));
        if ($code !== '') {
            $byCode[$code] = (int) $r['facility_id'];
        }
        $name = strtolower(trim((string) ($r['facility_name'] ?? '')));
        if ($name !== '' && !isset($byName[$name])) {
            $byName[$name] = (int) $r['facility_id'];
        }
    }
    return [$byCode, $byName];
}

/**
 * Validate one CSV row, resolve it to a facility id, and record its coordinates.
 *
 * @param array<int, string|null>            $row
 * @param array{code:?int,name:?int,lat:int,lon:int} $cols
 * @param array<string,int>                  $byCode
 * @param array<string,int>                  $byName
 * @param array<int, array{0:float,1:float}> $updates
 * @param array<string,int>                  $stats
 */
function csvProcessRow(array $row, array $cols, array $byCode, array $byName, array &$updates, array &$stats): void
{
    $latRaw = trim((string) ($row[$cols['lat']] ?? ''));
    $lonRaw = trim((string) ($row[$cols['lon']] ?? ''));
    if (!is_numeric($latRaw) || !is_numeric($lonRaw)
        || !NominatimGeocoderUtility::isValidCoord((float) $latRaw, (float) $lonRaw)) {
        $stats['badCoord']++;
        return;
    }

    $fid = null;
    if ($cols['code'] !== null) {
        $code = strtolower(trim((string) ($row[$cols['code']] ?? '')));
        if ($code !== '' && isset($byCode[$code])) {
            $fid = $byCode[$code];
            $stats['byCode']++;
        }
    }
    if ($fid === null && $cols['name'] !== null) {
        $name = strtolower(trim((string) ($row[$cols['name']] ?? '')));
        if ($name !== '' && isset($byName[$name])) {
            $fid = $byName[$name];
            $stats['byName']++;
        }
    }
    if ($fid === null) {
        $stats['noMatch']++;
        return;
    }

    $updates[$fid] = [round((float) $latRaw, 6), round((float) $lonRaw, 6)];
}

/**
 * Fan out facilities that share an identical coordinate using the sunflower
 * spread, mutating $updates in place. Returns how many points were moved.
 *
 * @param array<int, array{0: float, 1: float}> $updates
 */
function spreadDuplicateCoordinates(array &$updates): int
{
    $groups = [];
    foreach ($updates as $fid => [$lat, $lon]) {
        $groups[$lat . ',' . $lon][] = $fid;
    }

    $moved = 0;
    foreach ($groups as $ids) {
        $count = count($ids);
        if ($count < 2) {
            continue;
        }
        sort($ids); // deterministic ordering
        foreach ($ids as $index => $fid) {
            [$lat, $lon] = $updates[$fid];
            $offset = NominatimGeocoderUtility::sunflowerOffset($lat, $lon, $index, $count);
            $updates[$fid] = [round($offset['lat'], 6), round($offset['lon'], 6)];
            $moved++;
        }
    }
    return $moved;
}

ini_set('memory_limit', -1);
set_time_limit(0);

$options = getopt('', ['country::', 'limit::', 'redo', 'sleep::', 'dry-run', 'quiet', 'all', 'csv::', 'spread']);
$csvPath = isset($options['csv']) ? trim((string) $options['csv']) : '';
// Fan out facilities that share identical coordinates (e.g. LLM-filled district
// guesses) so they don't stack on one point. Applies to --csv import.
$spread = isset($options['spread']);
$country = isset($options['country']) ? trim((string) $options['country']) : null;
$limit = isset($options['limit']) ? max(0, (int) $options['limit']) : 0;
$redo = isset($options['redo']);
$sleep = isset($options['sleep']) ? max(0.0, (float) $options['sleep']) : 1.1;
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
// By default only geocode facilities that have ever recorded data (i.e. appear
// as a referring facility or testing lab in a test form). Many facilities in the
// register are never used and would just waste lookups. Pass --all to include them.
$allFacilities = isset($options['all']);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if ($db->isConnected() === false) {
    exit("Database connection failed. Please check your database settings.\n");
}

// CSV import mode: read coordinates from a file (no external API) and match to
// facilities by code (then name). Bypasses the Nominatim flow entirely.
if ($csvPath !== '') {
    importCoordinatesFromCsv($db, $csvPath, $dryRun, $quiet, $spread);
    exit(0);
}

// Infer the country if not supplied: use the instance's configured country
// (global_config.vl_form -> s_available_country_forms.form_name), falling back
// to the most common non-empty facility_details.country value.
if ($country === null || $country === '') {
    $row = $db->rawQueryOne(
        "SELECT TRIM(scf.form_name) AS country
           FROM s_available_country_forms scf
           JOIN global_config gc ON gc.name = 'vl_form' AND gc.value = scf.vlsm_country_id
          LIMIT 1"
    );
    $country = trim((string) ($row['country'] ?? ''));

    if ($country === '') {
        $row = $db->rawQueryOne(
            "SELECT country, COUNT(*) c
               FROM facility_details
              WHERE country IS NOT NULL AND TRIM(country) <> ''
           GROUP BY country
           ORDER BY c DESC
              LIMIT 1"
        );
        $country = trim((string) ($row['country'] ?? ''));
    }

    if ($country !== '') {
        echo "Using configured country: {$country}\n";
    } else {
        echo "No country supplied or inferable — lookups will be unconstrained (less accurate).\n";
    }
}

// Build the candidate filter.
$conditions = [];
if (!$redo) {
    $conditions[] = "(latitude IS NULL OR latitude = '' OR longitude IS NULL OR longitude = '')";
}
if (!$allFacilities) {
    $inUse = collectReferredFacilityIds($db);
    if ($inUse === []) {
        exit("No facilities have recorded any data — nothing to geocode (use --all to geocode every facility).\n");
    }
    $conditions[] = 'facility_id IN (' . implode(',', $inUse) . ')';
    echo "Scope: " . count($inUse) . " facilities that have recorded data (use --all to include unused ones).\n";
}
$where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

// Pull facilities ordered so the sunflower spread is stable across runs.
// Facilities WITH a district/province come first: they can use the centroid
// fallback (so they always get mapped) and are the ones that actually appear in
// referral flows. Facilities with no geo at all are attempted last, best-effort.
$facilities = $db->rawQuery(
    "SELECT facility_id, facility_name, facility_district, facility_state
       FROM facility_details
       $where
   ORDER BY (facility_district IS NULL OR facility_district = '') ASC,
            facility_state, facility_district, facility_id"
);

if (empty($facilities)) {
    exit("Nothing to do — all facilities already have coordinates (use --redo to override).\n");
}

// Pre-compute the sunflower slot (index + count) per district so each facility
// gets a reserved, deterministic position even if only some need the fallback.
$districtGroups = [];
foreach ($facilities as $f) {
    $key = strtolower(trim((string) $f['facility_state']) . '|' . trim((string) $f['facility_district']));
    $districtGroups[$key][] = (int) $f['facility_id'];
}
$slotIndex = [];   // facility_id => index within its district
$slotCount = [];   // district key => total facilities in district
foreach ($districtGroups as $key => $ids) {
    $slotCount[$key] = count($ids);
    foreach ($ids as $i => $fid) {
        $slotIndex[$fid] = $i;
    }
}

$centroidCache = [];   // "district|province|country" => ['lat','lon'] | false
$total = $limit > 0 ? min($limit, count($facilities)) : count($facilities);

echo "Geocoding {$total} facilit" . ($total === 1 ? 'y' : 'ies')
    . ($dryRun ? ' (DRY RUN)' : '') . " — this respects Nominatim's 1 req/sec limit, so expect ~"
    . ceil($total * $sleep / 60) . " min.\n\n";

$stats = ['facility' => 0, 'district' => 0, 'province' => 0, 'failed' => 0];
$processed = 0;

/** Geocode a place name once and cache the result. Returns [lat,lon] or null. */
$cachedGeocode = static function (string $cacheKey, string $query) use (&$centroidCache, $sleep): ?array {
    if (array_key_exists($cacheKey, $centroidCache)) {
        return $centroidCache[$cacheKey] ?: null;
    }
    $hit = NominatimGeocoderUtility::geocode($query);
    usleep((int) ($sleep * 1_000_000));
    $centroidCache[$cacheKey] = $hit ?: false;
    return $hit;
};

foreach ($facilities as $f) {
    if ($limit > 0 && $processed >= $limit) {
        break;
    }
    $processed++;

    $fid = (int) $f['facility_id'];
    $name = trim((string) $f['facility_name']);
    $district = trim((string) $f['facility_district']);
    $province = trim((string) $f['facility_state']);

    $parts = array_values(array_filter([$name, $district, $province, $country], static fn($p) => $p !== ''));
    $query = implode(', ', $parts);

    $lat = $lon = null;
    $source = 'failed';

    // 1. Facility-level lookup.
    $hit = NominatimGeocoderUtility::geocode($query);
    usleep((int) ($sleep * 1_000_000));
    if ($hit !== null) {
        $lat = $hit['lat'];
        $lon = $hit['lon'];
        $source = 'facility';
    }

    // 2. District centroid + sunflower spread.
    if ($lat === null && $district !== '') {
        $key = strtolower("$district|$province|$country");
        $dParts = array_values(array_filter([$district, $province, $country], static fn($p) => $p !== ''));
        $centroid = $cachedGeocode($key, implode(', ', $dParts));
        if ($centroid !== null) {
            $dKey = strtolower("$province|$district");
            $spread = NominatimGeocoderUtility::sunflowerOffset(
                $centroid['lat'],
                $centroid['lon'],
                $slotIndex[$fid] ?? 0,
                $slotCount[$dKey] ?? 1
            );
            $lat = $spread['lat'];
            $lon = $spread['lon'];
            $source = 'district';
        }
    }

    // 3. Province centroid + sunflower spread.
    if ($lat === null && $province !== '') {
        $key = strtolower("|$province|$country");
        $pParts = array_values(array_filter([$province, $country], static fn($p) => $p !== ''));
        $centroid = $cachedGeocode($key, implode(', ', $pParts));
        if ($centroid !== null) {
            $spread = NominatimGeocoderUtility::sunflowerOffset(
                $centroid['lat'],
                $centroid['lon'],
                $fid % 360,
                max(50, $total),
                0.12
            );
            $lat = $spread['lat'];
            $lon = $spread['lon'];
            $source = 'province';
        }
    }

    $stats[$source]++;

    if (!$quiet) {
        $label = str_pad("[$processed/$total]", 12);
        if ($lat === null) {
            echo "$label  SKIP   {$name} — no location found\n";
        } else {
            printf("%s  %-9s %s -> %.5f, %.5f\n", $label, strtoupper($source), $name, $lat, $lon);
        }
    }

    if ($lat !== null && !$dryRun) {
        $db->where('facility_id', $fid);
        $db->update('facility_details', [
            'latitude' => (string) round($lat, 6),
            'longitude' => (string) round($lon, 6),
        ]);
    }
}

echo "\n--------------------------------------------------\n";
echo $dryRun ? "DRY RUN — no rows written.\n" : "Done.\n";
echo "  Exact (facility) hits : {$stats['facility']}\n";
echo "  District fallback     : {$stats['district']}\n";
echo "  Province fallback     : {$stats['province']}\n";
echo "  Unlocated (skipped)   : {$stats['failed']}\n";
echo "--------------------------------------------------\n";
