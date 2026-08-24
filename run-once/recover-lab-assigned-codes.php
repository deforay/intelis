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
 *   1. audit_log  -- UPDATE ... JOIN per table+column, in batches of primary
 *      keys so no single statement holds a long metadata lock on the form
 *      table. Covers everything not yet drained to the archive.
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
 * Gated on the instance's country (lab assigned code is Cameroon-only), so on
 * every other install this is one indexed lookup and then a permanent no-op.
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

/**
 * How many samples the audit_log pass repairs per statement.
 *
 * Bounds how long any one statement holds a metadata lock on the form table.
 * Every DDL on that table -- the audit-trigger drop in `composer post-update`
 * above all -- has to wait for that lock, and once the DDL is queued MySQL
 * makes every later read of the table queue behind it too.
 */
const AUDIT_BATCH_SIZE = 500;

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

// ----- 3. Gate on the instance's country -----
// The bug this repairs only ever touched lab assigned code, which is a
// Cameroon-only field, so every other instance has nothing to recover and is
// marked complete without reading a single row of sample or audit data.
//
// The country is the gate rather than "is the column populated anywhere",
// which is what this used to ask. Answering that meant JSON_EXTRACT over every
// row of audit_log -- no index can serve those predicates -- run on every
// instance purely to conclude that there was nothing to do. The country lookup
// reads two indexed rows.
if (!isCameroonInstance($db)) {
    $log('Lab assigned code is a Cameroon-only field and this is not a Cameroon instance — marking complete.');
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
 * still carried a value wins.
 *
 * Batched, deliberately. The set-based version of this ran one statement per
 * column across the whole of audit_log, which meant a window function over
 * every row of a multi-million-row table while holding a metadata lock on the
 * form table. On a busy STS that lock lasted minutes, and any DDL queued behind
 * it -- `composer post-update`'s audit-trigger drop, in the case that surfaced
 * this -- took every subsequent read of the form table down with it, because
 * MySQL grants metadata locks in request order.
 *
 * Working in batches of primary keys keeps each statement short and each lock
 * brief. Candidates are read from the form table alone (indexed, bounded, no
 * audit_log involvement), then audit_log is probed for just those record ids,
 * which the `u_rec_rev` (form_table, record_id, revision) unique key covers.
 *
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

    $recovered = 0;
    $cursor = 0;

    while (true) {
        // Candidate ids only. A row this pass cannot fill (no surviving audit
        // revision) stays empty and would be selected forever without the
        // cursor, so paging is by primary key rather than by repeated LIMIT.
        $candidates = $db->rawQuery(
            "SELECT f.`$primaryKey` AS pk
             FROM   `$table` f
             WHERE  f.`$primaryKey` > ?
               AND  (f.`$column` IS NULL OR TRIM(f.`$column`) = '')
               AND  ($originWhere)
             ORDER BY f.`$primaryKey` ASC
             LIMIT " . AUDIT_BATCH_SIZE,
            [$cursor]
        );

        if (empty($candidates)) {
            break;
        }

        $ids = array_map(static fn(array $row): int => (int) $row['pk'], $candidates);
        $cursor = (int) end($ids);

        // record_id is VARCHAR, so the ids are bound as strings. Binding them as
        // integers makes MySQL convert the column instead of the literal, and a
        // converted column cannot use `u_rec_rev` -- turning the probe this
        // batching exists to avoid back into a full scan.
        $auditIds = array_map(strval(...), $ids);
        $auditPlaceholders = implode(',', array_fill(0, count($auditIds), '?'));
        $pkPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        $db->rawQuery(
            "UPDATE `$table` f
             JOIN (
                 SELECT record_id, val FROM (
                     SELECT a.record_id,
                            JSON_UNQUOTE(JSON_EXTRACT(a.row_data, ?)) AS val,
                            ROW_NUMBER() OVER (PARTITION BY a.record_id ORDER BY a.revision DESC) AS rn
                     FROM   audit_log a
                     WHERE  a.form_table = ?
                       AND  a.record_id IN ($auditPlaceholders)
                       AND  JSON_EXTRACT(a.row_data, ?) IS NOT NULL
                       AND  JSON_TYPE(JSON_EXTRACT(a.row_data, ?)) <> 'NULL'
                       AND  TRIM(JSON_UNQUOTE(JSON_EXTRACT(a.row_data, ?))) <> ''
                 ) ranked
                 WHERE ranked.rn = 1
             ) recovered ON recovered.record_id = f.`$primaryKey`
             SET   f.`$column` = recovered.val
             WHERE f.`$primaryKey` IN ($pkPlaceholders)
               AND (f.`$column` IS NULL OR TRIM(f.`$column`) = '')
               AND ($originWhere)",
            [$path, $table, ...$auditIds, $path, $path, $path, ...$ids]
        );

        // Read back rather than trusting an affected-row count: rawQuery() runs
        // the UPDATE through a prepared statement whose result binding zeroes
        // MysqliDb::$count. Every id in the batch was empty a statement ago, so
        // anything non-empty now was filled by the UPDATE above. Bounded to the
        // batch, and by primary key, so it is a cheap lookup either way.
        $filled = $db->rawQueryOne(
            "SELECT COUNT(*) AS filled
             FROM   `$table`
             WHERE  `$primaryKey` IN ($pkPlaceholders)
               AND  `$column` IS NOT NULL AND TRIM(`$column`) <> ''",
            $ids
        );
        $recovered += (int) ($filled['filled'] ?? 0);

        if (count($ids) < AUDIT_BATCH_SIZE) {
            break;
        }
    }

    return $recovered;
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

/**
 * Is this a Cameroon instance?
 *
 * Reads the configured country form (global_config.vl_form ->
 * s_available_country_forms.short_name), the same pairing the rest of the
 * application uses to decide which country's request forms to render.
 *
 * Fails closed: an instance whose country cannot be determined is not treated
 * as Cameroon, so the recovery is skipped and marked complete rather than
 * running a repair no other country needs.
 */
function isCameroonInstance(DatabaseService $db): bool
{
    try {
        $row = $db->rawQueryOne(
            "SELECT LOWER(TRIM(scf.short_name)) AS short_name
               FROM s_available_country_forms scf
               JOIN global_config gc
                 ON gc.name = 'vl_form' AND gc.value = scf.vlsm_country_id
              LIMIT 1"
        );

        return ($row['short_name'] ?? '') === 'cameroon';
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
