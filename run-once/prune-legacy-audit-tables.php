<?php

declare(strict_types=1);

/**
 * run-once/prune-legacy-audit-tables.php
 *
 * Audit Trail v2 — one-time aggressive prune of the legacy audit_form_* tables.
 *
 * Lives under run-once/ (NOT bin/) — this is a migration concern, not a
 * recurring job. The script is **idempotent, resumable across upgrades, and
 * self-completing**: each upgrade it runs, does as much as it can, and marks
 * itself done in `global_config` only once every legacy `audit_form_*` table
 * has been drained and dropped. A large backlog may take several upgrades.
 *
 * Algorithm per upgrade run:
 *   1. If `global_config.audit_legacy_prune_done = 'yes'`, exit (no-op).
 *   2. Run AuditArchiveService::run() — archives every audit_form_* table into
 *      the existing compressed CSV file store (var/audit-trail/...) with the
 *      service's own metadata-tracked last_processed_date.
 *   3. For each legacy audit_form_* that exists:
 *        a. DELETE rows with dt_datetime <= last_processed_date (the archive's
 *           guaranteed high-water mark).
 *        b. If the table is empty -> DROP TABLE (instant OS-level space reclaim).
 *      Tables that aren't yet empty are left in place for next upgrade.
 *   4. If every legacy table is now gone, set
 *      `global_config.audit_legacy_prune_done = 'yes'` so subsequent runs
 *      short-circuit.
 *
 * Trade-off intentionally accepted: the archive's de-dup is by dt_datetime
 * (second granularity), so two distinct revisions written in the same second
 * could be collapsed into one file row, and the corresponding `DELETE …
 * dt_datetime <= last_processed_date` would remove BOTH. This matches the
 * existing legacy archive's behavior (which has been live in production), so
 * the prune does not introduce a new risk class beyond what's already there;
 * v2 capture (audit_log) does NOT have this issue (UNIQUE per
 * record_id+revision).
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Registries\ContainerRegistry;
use App\Services\AuditArchiveService;
use App\Services\DatabaseService;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;

// @run-once-background
// upgrade.sh / docker/entrypoint.sh detect this marker and launch this script
// DETACHED (nohup … &), then continue immediately. On a big instance the
// archive can move 100K+ legacy audit rows and take many minutes; firing it in
// the background guarantees the upgrade is never blocked — not even for the
// second it takes PHP to boot. Because the loop fires-and-forgets, this script
// owns its concurrency (lock file) and self-skips when complete; its exit code
// is not observed by the loop.

$cliMode = php_sapi_name() === 'cli';

// Concurrency guard (previously the lock was claimed by forkToBackground): if a
// prune launched by a prior or overlapping upgrade is still running, don't start
// a second one on top of it. The lock is refreshed by the archive as it works
// and released on signals / shutdown.
$lockFile = MiscUtility::getLockFile(__FILE__);
if (!MiscUtility::isLockFileExpired($lockFile)) {
    exit(0);
}
MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);
register_shutdown_function(static function () use ($lockFile): void {
    MiscUtility::deleteLockFile($lockFile);
});

$log = static function (string $msg) use ($cliMode): void {
    if ($cliMode) {
        echo "[prune-legacy-audit] {$msg}\n";
    }
};

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var AuditArchiveService $svc */
$svc = ContainerRegistry::get(AuditArchiveService::class);

// ----- 1. Short-circuit if already complete -----
try {
    // rawQueryOne()['value'], not rawQueryValue() (which returns an array of
    // the column unless the query ends in "limit 1").
    $flagRow = $db->rawQueryOne(
        "SELECT value FROM global_config WHERE name = 'audit_legacy_prune_done'"
    );
    $flag = is_array($flagRow) ? ($flagRow['value'] ?? null) : null;
    if (is_string($flag) && strtolower(trim($flag)) === 'yes') {
        $log('Already complete on this instance; skipping.');
        exit(0);
    }
} catch (Throwable) {
    // global_config absent on extremely minimal installs — proceed.
}

// ----- Enumerate legacy audit_form_* tables present on this instance -----
$legacyTables = []; // audit_form_X => true
foreach (TestsService::getTestTypes() as $meta) {
    $form = $meta['tableName'] ?? null;
    if (!is_string($form) || $form === '') {
        continue;
    }
    $audit = 'audit_' . $form;
    if (isset($legacyTables[$audit])) {
        continue;
    }
    $exists = $db->rawQueryValue(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        [$audit]
    );
    if ($exists) {
        $legacyTables[$audit] = true;
    }
}

if ($legacyTables === []) {
    // Nothing to prune — fresh install or earlier upgrade already finished the job.
    $log('No legacy audit_form_* tables present — marking prune complete.');
    markPruneDone($db, $log);
    exit(0);
}

$log('Legacy tables present: ' . implode(', ', array_keys($legacyTables)));

// ----- 2. Archive all legacy rows to files via the existing service -----
$log('Archiving legacy audit data to var/audit-trail/* …');
try {
    $svc->run(progress: $log, useLock: false);
} catch (Throwable $e) {
    $log('Archive run failed: ' . $e->getMessage());
    LoggerUtility::logError('legacy-audit-prune: archive failed', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    // Don't mark done — next upgrade retries.
    exit(1);
}

// ----- 3. DELETE archived rows and DROP empty tables -----
$metadataPath = VAR_PATH . '/metadata/archive.mdata.json';
$metadata = MiscUtility::loadMetadata($metadataPath);

// Scalar row count. rawQueryValue() returns an ARRAY of the column unless the
// query ends in "limit 1" — `(int) [0 => 0]` casts to 1, so a bare
// rawQueryValue(COUNT(*)) is never === 0. rawQueryOne()['c'] is unambiguous.
$rowCount = static function (string $table) use ($db): int {
    $row = $db->rawQueryOne("SELECT COUNT(*) AS c FROM `{$table}`");
    return (int) ($row['c'] ?? 0);
};

$allDropped = true;
foreach (array_keys($legacyTables) as $auditTable) {
    try {
        // Already-empty legacy table: drained in a prior run (or never held
        // rows), so the archive produced no last_processed_date for it. It is
        // nonetheless safe to drop — do so directly instead of skipping it
        // forever on the missing-high-water-mark guard below.
        if ($rowCount($auditTable) === 0) {
            $db->rawQuery("DROP TABLE `{$auditTable}`");
            $log("  {$auditTable}: already empty — dropped.");
            continue;
        }

        $lastDt = $metadata[$auditTable]['last_processed_date'] ?? null;
        if (!is_string($lastDt) || $lastDt === '') {
            $log("  {$auditTable}: rows present but no last_processed_date — will retry next upgrade.");
            $allDropped = false;
            continue;
        }

        $db->rawQuery(
            "DELETE FROM `{$auditTable}` WHERE dt_datetime <= ?",
            [$lastDt]
        );
        $remaining = $rowCount($auditTable);
        if ($remaining === 0) {
            $db->rawQuery("DROP TABLE `{$auditTable}`");
            $log("  {$auditTable}: drained + dropped (OS-level space reclaimed).");
        } else {
            $log("  {$auditTable}: deleted archived rows; {$remaining} unprocessed remain — resuming next upgrade.");
            $allDropped = false;
        }
    } catch (Throwable $e) {
        $log("  {$auditTable}: error during DELETE/DROP — {$e->getMessage()}");
        LoggerUtility::logError("legacy-audit-prune: table {$auditTable} failed", [
            'message' => $e->getMessage(),
        ]);
        $allDropped = false;
    }
}

// ----- 4. Mark done iff every legacy table is gone -----
if ($allDropped) {
    markPruneDone($db, $log);
    $log('All legacy audit_form_* tables drained and dropped — prune complete.');
} else {
    $log('Some legacy tables still have rows or could not be dropped — will continue on next upgrade.');
}

exit(0);


function markPruneDone(DatabaseService $db, callable $log): void
{
    try {
        $db->rawQuery(
            "INSERT INTO global_config
                (name, display_name, value, category, remote_sync_needed, updated_datetime, status)
             VALUES
                ('audit_legacy_prune_done', 'Audit Trail v2 — Legacy Prune Complete', 'yes', 'general', 'no', NOW(), 'active')
             ON DUPLICATE KEY UPDATE value='yes', updated_datetime=NOW()"
        );
    } catch (Throwable $e) {
        $log('Could not write done-flag to global_config: ' . $e->getMessage());
        LoggerUtility::logError('legacy-audit-prune: could not write done-flag', [
            'message' => $e->getMessage(),
        ]);
    }
}
