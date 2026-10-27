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
 *   2. Drop any legacy `_data__` trigger still in the schema.
 *   3. For each legacy audit_form_* that exists:
 *        a. Copy the rows the legacy archive has not written to var/audit-trail
 *           (from its high-water mark on) into audit_log, in batches, and delete
 *           each batch once copied. The cron drain files them from there.
 *        b. DROP TABLE. The rows left are the ones the files already hold.
 *      A crashed MyISAM table is repaired and the move tried once more.
 *   4. If every legacy table is now gone, set
 *      `global_config.audit_legacy_prune_done = 'yes'` so subsequent runs
 *      short-circuit.
 *
 * The legacy archive de-duplicates by dt_datetime, at second granularity, and
 * the drain applies the same rule to moved rows, so two distinct revisions
 * written in the same second can still be collapsed into one file row. That
 * matches the legacy archive's behaviour since it went live. audit_log rows
 * written by the live triggers are keyed by record_id and revision and do not
 * have this issue.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Registries\ContainerRegistry;
use App\Services\AuditArchiveService;
use App\Services\AuditTriggerService;
use App\Services\DatabaseService;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;

// @run-once-background
// @run-once-done-flag audit_legacy_prune_done
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

// ----- 2. Drop any legacy trigger still writing to these tables -----
// upgrade.sh already drops them when it reinstalls the audit triggers. This makes
// sure of it before rows are moved, so nothing can write to a table being emptied.
$triggers = AuditTriggerService::instance();
foreach ($triggers->findLegacyTriggers() as $legacy) {
    $db->mysqli()->query($triggers->buildDropLegacyTriggerByName($legacy['trigger']));
    $log("  dropped legacy trigger {$legacy['trigger']} on {$legacy['table']}");
}

// ----- 3. Move what the files do not hold into audit_log, then drop the table -----
//
// AuditArchiveService::moveLegacyTableToAuditLog() copies the rows the files do
// not hold into audit_log, where the cron drain files them like any other audit
// row, and drops the table. One INSERT ... SELECT per batch moves millions of
// rows in minutes. Archiving them row by row in PHP kept these tables for months.
//
// A crashed MyISAM table is repaired first, then the move is tried again. Older
// installs created some of these tables as MyISAM, which marks a table crashed
// after an unclean shutdown, and every read of it fails until it is repaired. One
// lab had 3.7 million rows in a crashed audit_form_vl.
$archive = ContainerRegistry::get(AuditArchiveService::class);

$formPk = [];
foreach (TestsService::getTestTypes() as $meta) {
    if (is_string($meta['tableName'] ?? null) && is_string($meta['primaryKey'] ?? null)) {
        $formPk[$meta['tableName']] = $meta['primaryKey'];
    }
}

$moveTable = static function (string $auditTable) use ($archive, $log, $formPk, $lockFile): void {
    $formTable = substr($auditTable, strlen('audit_'));
    $pk = $formPk[$formTable] ?? throw new RuntimeException("no primary key known for {$formTable}");
    $moved = $archive->moveLegacyTableToAuditLog(
        $auditTable,
        $formTable,
        $pk,
        $archive->legacyHighWaterMark($auditTable),
        static fn() => MiscUtility::touchLockFile($lockFile)
    );
    $log("  {$auditTable}: moved {$moved} row(s) to audit_log, dropped the table.");
};

$repairCrashed = static function (Throwable $e, string $auditTable) use ($db, $log): bool {
    if (preg_match('/crashed|try to repair/i', $e->getMessage()) !== 1) {
        return false;
    }
    $log("  {$auditTable}: marked as crashed, repairing (this can take several minutes) …");
    // Not prepared: MySQL refuses REPAIR over the prepared-statement protocol.
    $result = $db->mysqli()->query("REPAIR TABLE `{$auditTable}`")->fetch_all(MYSQLI_ASSOC);
    $log("  {$auditTable}: " . implode('; ', array_column($result, 'Msg_text')));
    return true;
};

$allDropped = true;
foreach (array_keys($legacyTables) as $auditTable) {
    try {
        try {
            $moveTable($auditTable);
        } catch (Throwable $e) {
            if (!$repairCrashed($e, $auditTable)) {
                throw $e;
            }
            $moveTable($auditTable);
        }
    } catch (Throwable $e) {
        $log("  {$auditTable}: could not move — {$e->getMessage()}");
        LoggerUtility::logError("legacy-audit-prune: table {$auditTable} failed", [
            'message' => $e->getMessage(),
        ]);
        $allDropped = false;
    }
}

// ----- 4. Mark done iff every legacy table is gone -----
if ($allDropped) {
    markPruneDone($db, $log);
    $log('All legacy audit_form_* tables moved and dropped — prune complete.');
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
