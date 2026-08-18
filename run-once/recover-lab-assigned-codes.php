<?php

declare(strict_types=1);

/**
 * run-once/recover-lab-assigned-codes.php
 *
 * One-time repair for lab assigned codes (and VL CV numbers) blanked by the
 * STS request sync before commit 1ab4f904b.
 *
 * The sync seeded every column as null, STS sent its own still-empty copy of
 * lab_assigned_code, and the column was in neither removeKeys nor
 * excludeUpdateKeys -- so a routine pull wrote NULL over the code the lab had
 * just entered. Only samples that came down from STS were ever pulled back,
 * which is why manually registered samples kept theirs.
 *
 * Recovery runs in two passes, cheapest first:
 *   1. audit_log  -- set-based UPDATE ... JOIN per table+column. Fast, covers
 *      everything not yet drained to the archive.
 *   2. archives   -- var/audit-trail/{testKey}/{unique_id}.csv.{zst|gz|zip},
 *      read one sample at a time via AuditArchiveService. Bounded per run and
 *      resumable through a cursor, so a large backlog spreads across upgrades
 *      the way prune-legacy-audit-tables.php does.
 *
 * Safety properties:
 *   - Only ever fills a column that is currently empty. Never overwrites.
 *   - Only touches samples of STS origin (remote_sample_code / package code),
 *     the only ones the bug could reach, so a deliberate local clear on a
 *     manually registered sample is left alone.
 *   - The restore is itself an audited UPDATE, so it can be traced or undone.
 *
 * Gated on the field actually being in use (lab assigned code is Cameroon-only),
 * so on every other install this is two cheap queries per module and then a
 * permanent no-op.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Registries\ContainerRegistry;
use App\Services\AuditArchiveService;
use App\Services\DatabaseService;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Utilities\MiscUtility;

// @run-once-background
// The audit_log pass is quick, but the archive fallback opens one compressed
// file per sample and can run long on a lab with a big backlog. upgrade.sh
// detects this marker and launches the script detached so the upgrade is never
// blocked; the script therefore owns its own concurrency guard below.

$cliMode = php_sapi_name() === 'cli';

/** How many samples the archive pass reads per run, per table. */
const ARCHIVE_BATCH_SIZE = 500;

const DONE_FLAG = 'lab_code_recovery_done';
const CURSOR_FLAG = 'lab_code_recovery_cursor';

/** Columns to recover, in priority order. Skipped per table when absent. */
const RECOVER_COLUMNS = ['lab_assigned_code', 'cv_number'];

/** Any one of these being non-empty marks a sample as having come from STS. */
const ORIGIN_COLUMNS = ['remote_sample_code', 'sample_package_code', 'referral_manifest_code'];

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
        echo "[recover-lab-codes] {$msg}\n";
    }
};

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var AuditArchiveService $archiveService */
$archiveService = ContainerRegistry::get(AuditArchiveService::class);

// ----- 1. Short-circuit if already complete -----
if (strtolower((string) readConfig($db, DONE_FLAG)) === 'yes') {
    $log('Already complete on this instance; skipping.');
    exit(0);
}

// ----- 2. Build the work list: module tables that carry a recoverable column -----
$targets = [];
foreach (TestsService::getTestTypes() as $testType => $meta) {
    $table = $meta['tableName'] ?? null;
    $primaryKey = $meta['primaryKey'] ?? null;
    if (!is_string($table) || $table === '' || !is_string($primaryKey) || $primaryKey === '') {
        continue;
    }
    if (isset($targets[$table])) {
        continue; // aliased test keys pointing at the same table
    }

    $columns = tableColumns($db, $table);
    if ($columns === []) {
        continue; // table not present on this instance
    }

    $recoverable = array_values(array_intersect(RECOVER_COLUMNS, $columns));
    $originColumns = array_values(array_intersect(ORIGIN_COLUMNS, $columns));
    if ($recoverable === [] || $originColumns === [] || !in_array('unique_id', $columns, true)) {
        continue;
    }

    $targets[$table] = [
        'testType' => $testType,
        'primaryKey' => $primaryKey,
        'columns' => $recoverable,
        'originColumns' => $originColumns,
    ];
}

if ($targets === []) {
    $log('No module table carries a recoverable column — nothing to do.');
    markDone($db, $log);
    exit(0);
}

// ----- 3. Gate on the field being in use on this instance -----
// Lab assigned code is Cameroon-only. Checking the live table alone is not
// enough (an instance could in principle have had every value wiped), so the
// audit history counts as "in use" too.
$inUse = false;
foreach ($targets as $table => $cfg) {
    foreach ($cfg['columns'] as $column) {
        if (columnInUse($db, $table, $column)) {
            $inUse = true;
            break 2;
        }
    }
}

if (!$inUse) {
    $log('Lab assigned code is not used on this instance — marking complete.');
    markDone($db, $log);
    exit(0);
}

// ----- 4. Pass one: recover from audit_log -----
foreach ($targets as $table => $cfg) {
    foreach ($cfg['columns'] as $column) {
        try {
            $recovered = recoverFromAuditLog($db, $table, $cfg['primaryKey'], $column, $cfg['originColumns']);
            if ($recovered > 0) {
                $log("audit_log: restored {$recovered} {$column} value(s) in {$table}.");
            }
        } catch (Throwable $e) {
            $log("audit_log pass failed for {$table}.{$column}: " . $e->getMessage());
            LoggerUtility::logError('recover-lab-codes: audit_log pass failed', [
                'table' => $table,
                'column' => $column,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

// ----- 5. Pass two: archive fallback for whatever audit_log could not cover -----
$cursors = decodeCursors(readConfig($db, CURSOR_FLAG));
$allTablesExhausted = true;

foreach ($targets as $table => $cfg) {
    $cursor = (int) ($cursors[$table] ?? 0);

    try {
        [$scanned, $recovered, $newCursor] = recoverFromArchives(
            $db,
            $archiveService,
            $table,
            $cfg,
            $cursor,
            ARCHIVE_BATCH_SIZE
        );
    } catch (Throwable $e) {
        $log("archive pass failed for {$table}: " . $e->getMessage());
        LoggerUtility::logError('recover-lab-codes: archive pass failed', [
            'table' => $table,
            'message' => $e->getMessage(),
        ]);
        $allTablesExhausted = false;
        continue;
    }

    $cursors[$table] = $newCursor;

    if ($scanned > 0) {
        $log("archives: scanned {$scanned} sample(s) in {$table}, restored {$recovered}.");
    }

    // A short batch means we reached the end of this table's candidate list.
    if ($scanned >= ARCHIVE_BATCH_SIZE) {
        $allTablesExhausted = false;
    }
}

writeConfig($db, CURSOR_FLAG, (string) json_encode($cursors), 'Lab Assigned Code Recovery — Archive Cursor', $log);

if ($allTablesExhausted) {
    markDone($db, $log);
    $log('Recovery complete on every module table.');
} else {
    $log('More samples left to scan — will continue on the next upgrade.');
}

exit(0);


/**
 * Restore a column from audit_log: for each sample, the highest revision that
 * still carried a value wins. Set-based, so this is one statement per column.
 */
/**
 * @param list<string> $originColumns
 */
function recoverFromAuditLog(
    DatabaseService $db,
    string $table,
    string $primaryKey,
    string $column,
    array $originColumns
): int {
    // Column and table names come from TestsService / information_schema, never
    // from input, so they are safe to interpolate; values stay bound.
    $path = '$.' . $column;
    $originWhere = originCondition($originColumns);

    // Counted before the write rather than read back afterwards: rawQuery()
    // runs the UPDATE through a prepared statement whose result binding zeroes
    // MysqliDb::$count, so there is no affected-row count to read. This figure
    // only feeds the log line.
    $pending = $db->rawQueryOne(
        "SELECT COUNT(*) AS pending
         FROM `$table` f
         JOIN (
             SELECT record_id FROM (
                 SELECT a.record_id,
                        ROW_NUMBER() OVER (PARTITION BY a.record_id ORDER BY a.revision DESC) AS rn
                 FROM   audit_log a
                 WHERE  a.form_table = ?
                   AND  JSON_EXTRACT(a.row_data, ?) IS NOT NULL
                   AND  JSON_TYPE(JSON_EXTRACT(a.row_data, ?)) <> 'NULL'
                   AND  TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.row_data, ?))) <> ''
             ) ranked
             WHERE ranked.rn = 1
         ) recovered ON recovered.record_id = f.`$primaryKey`
         WHERE (f.`$column` IS NULL OR TRIM(f.`$column`) = '')
           AND ($originWhere)",
        [$table, $path, $path, $path]
    );

    $count = (int) ($pending['pending'] ?? 0);
    if ($count === 0) {
        return 0;
    }

    $db->rawQuery(
        "UPDATE `$table` f
         JOIN (
             SELECT record_id, val FROM (
                 SELECT a.record_id,
                        JSON_UNQUOTE(JSON_EXTRACT(a.row_data, ?)) AS val,
                        ROW_NUMBER() OVER (PARTITION BY a.record_id ORDER BY a.revision DESC) AS rn
                 FROM   audit_log a
                 WHERE  a.form_table = ?
                   AND  JSON_EXTRACT(a.row_data, ?) IS NOT NULL
                   AND  JSON_TYPE(JSON_EXTRACT(a.row_data, ?)) <> 'NULL'
                   AND  TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.row_data, ?))) <> ''
             ) ranked
             WHERE ranked.rn = 1
         ) recovered ON recovered.record_id = f.`$primaryKey`
         SET   f.`$column` = recovered.val
         WHERE (f.`$column` IS NULL OR TRIM(f.`$column`) = '')
           AND ($originWhere)",
        [$path, $table, $path, $path, $path]
    );

    return $count;
}

/**
 * Archive fallback. Walks candidates in primary-key order from $cursor, opens
 * each sample's archived audit CSV, and takes the highest revision that still
 * carried a value for any of the recoverable columns.
 *
 * @param array{testType: string, primaryKey: string, columns: list<string>, originColumns: list<string>} $cfg
 *
 * @return array{0: int, 1: int, 2: int} [scanned, recovered, new cursor]
 */
function recoverFromArchives(
    DatabaseService $db,
    AuditArchiveService $archiveService,
    string $table,
    array $cfg,
    int $cursor,
    int $limit
): array {
    $primaryKey = $cfg['primaryKey'];
    $originWhere = originCondition($cfg['originColumns']);

    // Any recoverable column still empty makes the sample a candidate.
    $emptyChecks = [];
    foreach ($cfg['columns'] as $column) {
        $emptyChecks[] = "(f.`$column` IS NULL OR TRIM(f.`$column`) = '')";
    }

    $candidates = $db->rawQuery(
        "SELECT f.`$primaryKey` AS pk, f.unique_id
         FROM   `$table` f
         WHERE  f.`$primaryKey` > ?
           AND  (" . implode(' OR ', $emptyChecks) . ")
           AND  ($originWhere)
           AND  f.unique_id IS NOT NULL AND f.unique_id <> ''
         ORDER BY f.`$primaryKey` ASC
         LIMIT $limit",
        [$cursor]
    );

    $scanned = 0;
    $recovered = 0;

    foreach ($candidates as $candidate) {
        $scanned++;
        $cursor = (int) $candidate['pk'];

        $path = $archiveService->resolveAuditFilePath($cfg['testType'], (string) $candidate['unique_id']);
        if ($path === null) {
            continue; // never archived, or archived under a layout we cannot resolve
        }

        $rows = $archiveService->readAuditDataFromCsvFlexible($path, $cfg['testType']);
        if ($rows === []) {
            continue;
        }

        // Highest revision first, so the first non-empty value we meet is the
        // last one the lab actually had. Legacy archives (written from the old
        // audit_form_* tables) have no revision column, so fall back to the
        // timestamp -- ISO-ish datetimes sort correctly as strings.
        usort($rows, static function (array $a, array $b): int {
            $byRevision = (int) ($b['revision'] ?? 0) <=> (int) ($a['revision'] ?? 0);

            return $byRevision !== 0
                ? $byRevision
                : strcmp((string) ($b['dt_datetime'] ?? ''), (string) ($a['dt_datetime'] ?? ''));
        });

        $restore = [];
        foreach ($cfg['columns'] as $column) {
            foreach ($rows as $row) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value !== '' && strtolower($value) !== 'null') {
                    $restore[$column] = $value;
                    break;
                }
            }
        }

        if ($restore === []) {
            continue;
        }

        // Re-check emptiness at write time: the audit_log pass may have filled
        // one of these columns already, and we never overwrite.
        $current = $db->rawQueryOne(
            "SELECT * FROM `$table` WHERE `$primaryKey` = ?",
            [$candidate['pk']]
        );
        foreach (array_keys($restore) as $column) {
            if (trim((string) ($current[$column] ?? '')) !== '') {
                unset($restore[$column]);
            }
        }

        if ($restore === []) {
            continue;
        }

        $db->where($primaryKey, $candidate['pk']);
        if ($db->update($table, $restore)) {
            $recovered++;
        }
    }

    return [$scanned, $recovered, $cursor];
}

/**
 * "at least one origin column is non-empty", built from the columns present.
 *
 * @param list<string> $originColumns
 */
function originCondition(array $originColumns): string
{
    $parts = [];
    foreach ($originColumns as $column) {
        $parts[] = "(f.`$column` IS NOT NULL AND TRIM(f.`$column`) <> '')";
    }

    return implode(' OR ', $parts);
}

/**
 * Column names of $table, empty when the table does not exist here.
 *
 * @return list<string>
 */
function tableColumns(DatabaseService $db, string $table): array
{
    try {
        $rows = $db->rawQuery(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
    } catch (Throwable) {
        return [];
    }

    return array_column($rows, 'COLUMN_NAME');
}

/** Is this column populated anywhere -- live table first, then audit history? */
function columnInUse(DatabaseService $db, string $table, string $column): bool
{
    try {
        $live = $db->rawQueryOne(
            "SELECT 1 AS found FROM `$table`
             WHERE `$column` IS NOT NULL AND TRIM(`$column`) <> '' LIMIT 1"
        );
        if (!empty($live)) {
            return true;
        }

        $audited = $db->rawQueryOne(
            "SELECT 1 AS found FROM audit_log
             WHERE form_table = ?
               AND JSON_EXTRACT(row_data, ?) IS NOT NULL
               AND JSON_TYPE(JSON_EXTRACT(row_data, ?)) <> 'NULL'
               AND TRIM(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?))) <> ''
             LIMIT 1",
            [$table, '$.' . $column, '$.' . $column, '$.' . $column]
        );

        return !empty($audited);
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return array<string, int>
 */
function decodeCursors(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $cursors = [];
    foreach ($decoded as $table => $cursor) {
        if (is_string($table) && is_numeric($cursor)) {
            $cursors[$table] = (int) $cursor;
        }
    }

    return $cursors;
}

function readConfig(DatabaseService $db, string $name): ?string
{
    try {
        $row = $db->rawQueryOne("SELECT value FROM global_config WHERE name = ?", [$name]);
        $value = is_array($row) ? ($row['value'] ?? null) : null;

        return is_string($value) ? trim($value) : null;
    } catch (Throwable) {
        return null; // global_config absent on extremely minimal installs
    }
}

function writeConfig(DatabaseService $db, string $name, string $value, string $displayName, callable $log): void
{
    try {
        $db->rawQuery(
            "INSERT INTO global_config
                (name, display_name, value, category, remote_sync_needed, updated_datetime, status)
             VALUES (?, ?, ?, 'general', 'no', NOW(), 'active')
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_datetime = NOW()",
            [$name, $displayName, $value]
        );
    } catch (Throwable $e) {
        $log("Could not write {$name} to global_config: " . $e->getMessage());
        LoggerUtility::logError('recover-lab-codes: could not write config', [
            'name' => $name,
            'message' => $e->getMessage(),
        ]);
    }
}

function markDone(DatabaseService $db, callable $log): void
{
    writeConfig($db, DONE_FLAG, 'yes', 'Lab Assigned Code Recovery Complete', $log);
}
