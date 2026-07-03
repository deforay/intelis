<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/*
 * Clean up pre-existing facility codes that predate sanitizeFacilityCode().
 *
 * Legacy codes were stored verbatim, so some carry leading/trailing spaces,
 * stray symbols, or doubled hyphens (e.g. " MOSALISI", "ABC / 1", "A--B").
 * The edit form preserves an untouched code as-is, so these never self-heal,
 * and a dirty code can hide a near-duplicate from the live uniqueness check.
 *
 * This normalises every existing code to the same form the save path now uses
 * (sanitizeFacilityCode: A-Z 0-9 and single internal hyphens, uppercased).
 *
 * Safety:
 *   - Rows already in normalised form are skipped (so it is idempotent).
 *   - A change is applied only when the target code is not already claimed by
 *     another facility (case-insensitive, matching the UNIQUE index). Codes that
 *     would collide, or that normalise to empty, are left untouched and listed
 *     for manual resolution rather than blanked or force-merged.
 */
RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $rows = $db->rawQuery(
        "SELECT facility_id, facility_code
         FROM facility_details
         WHERE facility_code IS NOT NULL AND facility_code <> ''"
    );
    $total = is_array($rows) ? count($rows) : 0;

    if ($total === 0) {
        MiscUtility::safeCliEcho("Normalising facility codes… none present." . PHP_EOL);
        return;
    }

    // Values that are already correct stay put; treat them as claimed so a
    // normalised code can never be moved onto an existing one.
    $taken = [];
    $dirty = [];
    foreach ($rows as $row) {
        $original = (string) $row['facility_code'];
        $normalized = $facilitiesService->sanitizeFacilityCode($original);
        if ($normalized === $original) {
            $taken[strtoupper($normalized)] = true;
        } else {
            $dirty[] = ['id' => (int) $row['facility_id'], 'original' => $original, 'normalized' => $normalized];
        }
    }

    if ($dirty === []) {
        MiscUtility::safeCliEcho("Normalising facility codes… all {$total} already clean." . PHP_EOL);
        return;
    }

    $bar = MiscUtility::spinnerStart(count($dirty), "Normalising facility codes");
    $updated = 0;
    $conflicts = [];

    foreach ($dirty as $item) {
        $key = strtoupper($item['normalized']);

        if ($item['normalized'] === '' || isset($taken[$key])) {
            // Empty result or a genuine clash: leave the original in place.
            $conflicts[] = $item;
            MiscUtility::spinnerAdvance($bar);
            continue;
        }

        $db->where('facility_id', $item['id']);
        $db->update('facility_details', ['facility_code' => $item['normalized']]);
        $taken[$key] = true;
        $updated++;

        MiscUtility::spinnerAdvance($bar);
    }

    MiscUtility::spinnerFinish($bar);
    MiscUtility::safeCliEcho("Normalised {$updated} facility code(s) of {$total}." . PHP_EOL);

    if ($conflicts !== []) {
        MiscUtility::safeCliEcho("Left " . count($conflicts) . " code(s) unchanged (empty or would collide) — resolve manually:" . PHP_EOL);
        foreach ($conflicts as $c) {
            $shown = $c['normalized'] === '' ? '(empty)' : $c['normalized'];
            MiscUtility::safeCliEcho("  facility_id {$c['id']}: \"{$c['original']}\" -> {$shown}" . PHP_EOL);
        }
    }
});
