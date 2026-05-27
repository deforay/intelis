#!/usr/bin/env php
<?php

/**
 * bin/geocode-facilities.php
 *
 * Approximate missing facility coordinates so they can be plotted on the
 * Sample Referral Network map (Admin -> Monitoring).
 *
 * Strategy (country-agnostic — works for any deployment, not just Rwanda):
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
 *   php bin/geocode-facilities.php [--country="Rwanda"] [--limit=N] [--redo]
 *                                 [--sleep=1.1] [--dry-run] [--quiet]
 *
 *   --country   Country name appended to every lookup. Disambiguates province /
 *               district names worldwide. If omitted, it is inferred from the
 *               most common non-empty facility_details.country value.
 *   --redo      Re-geocode facilities that already have coordinates.
 *   --limit     Process at most N facilities (handy for a quick test run).
 *   --sleep     Seconds between Nominatim calls (default 1.1, policy minimum 1).
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

ini_set('memory_limit', -1);
set_time_limit(0);

$options = getopt('', ['country::', 'limit::', 'redo', 'sleep::', 'dry-run', 'quiet', 'all']);
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
