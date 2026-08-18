#!/usr/bin/env php
<?php

// Rewrite historical samples whose rejection reason id belongs to the lab that
// sent them, using the map STS built from the labs' own reason tables.
//
// Why this is a command and not a run-once: it can only do anything after the
// labs have upgraded and their next metadata sync has pushed their reason
// tables up (lab-metadata-sender.php -> RejectionReasonMappingService). Bolting
// it to the upgrade would run it before the map exists. Run it once labs have
// reported in, and again later -- it is idempotent, and each run picks up
// whichever labs have synced since.
//
// STS only: nothing else holds the map.
//
// Usage:
//   php bin/apply-rejection-reason-map.php --dry-run   count what would change
//   php bin/apply-rejection-reason-map.php             apply it
//   php bin/apply-rejection-reason-map.php vl          one test type only

require_once __DIR__ . "/../bootstrap.php";

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\RejectionReasonMappingService;

if (PHP_SAPI !== 'cli') {
    exit(0);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$only = null;
foreach ($args as $arg) {
    if (!str_starts_with($arg, '--')) {
        $only = $arg;
        break;
    }
}

if (!$general->isSTSInstance()) {
    MiscUtility::safeCliEcho("Not an STS instance -- the map lives only on STS. Nothing to do." . PHP_EOL);
    exit(0);
}

// Each mapping is a (test type, lab, that lab's reason id) -> this server's id.
// Rows are updated one mapping at a time rather than by a join, so the work is
// visible, resumable and bounded: a mapping that turns out to be wrong can be
// corrected and re-applied without unpicking a bulk statement.
$mappings = $db->rawQuery(
    'SELECT test_type, lab_id, source_reason_id, rejection_reason_id
       FROM `' . RejectionReasonMappingService::MAP_TABLE . '`
      WHERE source_reason_id <> rejection_reason_id
      ORDER BY test_type, lab_id, source_reason_id'
);

if (empty($mappings)) {
    MiscUtility::safeCliEcho(
        "No mappings differ from the id already stored." . PHP_EOL
        . "Either no lab has synced its reason tables yet, or every id already agrees." . PHP_EOL
    );
    exit(0);
}

$totalRows = 0;
$applied = 0;
$skipped = [];

foreach ($mappings as $mapping) {
    $testType = (string) $mapping['test_type'];
    if ($only !== null && $testType !== $only) {
        continue;
    }
    try {
        $table = TestsService::getTestTableName($testType);
    } catch (Throwable) {
        $skipped[$testType] = true;
        continue;
    }

    $labId = (int) $mapping['lab_id'];
    $sourceId = (int) $mapping['source_reason_id'];
    $canonicalId = (int) $mapping['rejection_reason_id'];

    $countRow = $db->rawQueryOne(
        "SELECT COUNT(*) AS total FROM `$table`
          WHERE lab_id = ? AND reason_for_sample_rejection = ?",
        [$labId, $sourceId]
    );
    $affected = (int) ($countRow['total'] ?? 0);
    if ($affected === 0) {
        continue;
    }
    $totalRows += $affected;

    MiscUtility::safeCliEcho(
        sprintf(
            "%s lab %d: reason %d -> %d  (%d row%s)%s",
            $testType,
            $labId,
            $sourceId,
            $canonicalId,
            $affected,
            $affected === 1 ? '' : 's',
            PHP_EOL
        )
    );

    if ($dryRun) {
        continue;
    }

    // data_sync is deliberately NOT reset. The lab's own id is right on the lab,
    // and pushing this rewrite back would replace a reason it can read with one
    // it cannot. The translation belongs on this side only.
    $db->rawQuery(
        "UPDATE `$table`
            SET reason_for_sample_rejection = ?
          WHERE lab_id = ? AND reason_for_sample_rejection = ?",
        [$canonicalId, $labId, $sourceId]
    );
    $applied += $affected;
}

if ($skipped !== []) {
    MiscUtility::safeCliEcho(
        "Skipped unknown test type(s): " . implode(', ', array_keys($skipped)) . PHP_EOL
    );
}

MiscUtility::safeCliEcho(
    $dryRun
        ? sprintf("Dry run: %d row(s) would be rewritten.%s", $totalRows, PHP_EOL)
        : sprintf("Rewrote %d row(s).%s", $applied, PHP_EOL)
);

if (!$dryRun && $applied > 0) {
    LoggerUtility::logInfo('Applied lab rejection reason map to historical samples', [
        'rows' => $applied,
        'testType' => $only,
    ]);
}
