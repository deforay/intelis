<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/*
 * On STS, each testing lab's facility_code is appended to its sample codes
 * ("RVL0626-NMC19244"). Labs created without a code fell back to a meaningless
 * md5-of-id stub at sample-mint time. This backfills a stable, human-readable,
 * unique code (derived from the lab name) for every testing lab (facility_type = 2)
 * that still has no facility_code, so the postfix is consistent and recognisable.
 *
 * Idempotent: only rows with a NULL/empty facility_code are touched, so re-running
 * (or running after admins have set codes manually) is a no-op. The UNIQUE index on
 * facility_code is honoured by generateFacilityCode(), which disambiguates collisions.
 */
RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $labs = $db->rawQuery(
        "SELECT facility_id, facility_name
         FROM facility_details
         WHERE facility_type = 2
           AND (facility_code IS NULL OR TRIM(facility_code) = '')"
    );
    $total = is_array($labs) ? count($labs) : 0;
    $updated = 0;
    $skipped = 0;

    if ($total === 0) {
        MiscUtility::safeCliEcho("Backfilling facility codes… no testing labs need one." . PHP_EOL);
        return;
    }

    $bar = MiscUtility::spinnerStart($total, "Backfilling facility codes");

    foreach ($labs as $row) {
        $facilityId = (int) $row['facility_id'];
        $name = trim((string) ($row['facility_name'] ?? ''));

        $code = $facilitiesService->generateFacilityCode($name, $facilityId);
        if ($code === '') {
            $skipped++;
            MiscUtility::spinnerAdvance($bar);
            continue;
        }

        $db->where('facility_id', $facilityId);
        $db->update('facility_details', ['facility_code' => $code]);
        $updated++;

        MiscUtility::spinnerAdvance($bar);
    }

    MiscUtility::spinnerFinish($bar);
    MiscUtility::safeCliEcho("Backfilled {$updated} testing lab(s); skipped {$skipped} of {$total}." . PHP_EOL);
});
