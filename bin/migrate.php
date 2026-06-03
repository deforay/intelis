#!/usr/bin/env php
<?php

// Run pending database migrations.
//
// Usage:
//   php bin/migrate.php                  run pending migrations
//   php bin/migrate.php -y               auto-continue on error
//   php bin/migrate.php -q               quiet mode
//   php bin/migrate.php --status         show current version and pending files, exit
//   php bin/migrate.php --dry-run        preview statements without executing
//   php bin/migrate.php --version=4.5.0  replay from a specific version (ignores DB version)

require_once __DIR__ . "/../bootstrap.php";

// only run from command line
$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    exit(CLI\ERROR);
}

// Handle Ctrl+C gracefully (if pcntl extension is available)
if ($isCli && function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function (): void {
        echo PHP_EOL . PHP_EOL;
        echo "⚠️  Migration cancelled by user." . PHP_EOL;
        exit(CLI\SIGINT); // Standard exit code for SIGINT
    });
}

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$currentMajorVersion = $general->getAppVersion();

// Ensure the script only runs for VLSM APP VERSION >= 4.4.3
if (version_compare($currentMajorVersion, '4.4.3', '<')) {
    $io->error("This script requires VERSION 4.4.3 or higher. Current version: " . htmlspecialchars((string) $currentMajorVersion));
    exit(CLI\ERROR);
}

// Define the logs directory path
$logsDir = LOG_PATH;

const MIG_NOT_HANDLED = 0;
const MIG_EXECUTED = 1;
const MIG_SKIPPED = 2;

// Initialize a flag to determine if logging is possible
$canLog = false;

// Check if the directory exists
if (!file_exists($logsDir)) {
    if (!MiscUtility::makeDirectory($logsDir)) {
        $io->error("Failed to create directory: $logsDir");
    } else {
        $io->success("Directory created: $logsDir");
        $canLog = file_exists($logsDir) && is_writable($logsDir);
    }
} else {
    $canLog = file_exists($logsDir) && is_writable($logsDir);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Check if connection was successful
if ($db->isConnected() === false) {
    $io->error("Database connection failed. Please check your database settings");
    exit(CLI\ERROR);
}

/* ---------------------- Local helpers (idempotent DDL) ---------------------- */

function current_db(DatabaseService $db): string
{
    static $dbName = null;
    if ($dbName === null) {
        $dbName = $db->rawQueryOne('SELECT DATABASE() AS db')['db'] ?? '';
    }
    return $dbName;
}

/** Common handler for both ADD PRIMARY KEY syntaxes. */
function _apply_add_primary_key(DatabaseService $db, SymfonyStyle $io, string $table, string $colsList, string $originalSql): int
{
    $wantedCols = parse_cols_list($colsList);
    $haveCols = table_primary_key($db, $table);

    if ($haveCols === []) {
        $db->rawQuery($originalSql);
        assert_no_errno($db, $originalSql);
        return MIG_EXECUTED;
    }
    if ($haveCols === $wantedCols) {
        return MIG_SKIPPED;
    }

    if (getenv('MIG_REPLACE_PK')) {
        $sql = "ALTER TABLE `{$table}` DROP PRIMARY KEY";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);

        $colsSql = implode(',', array_map(static fn($c): string => "`$c`", $wantedCols));
        $sql = "ALTER TABLE `{$table}` ADD PRIMARY KEY ($colsSql)";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);
        return MIG_EXECUTED;
    }

    if (getenv('MIG_VERBOSE')) {
        $io->comment("NOTE: Skipping PK change on {$table} (have: "
            . implode(',', $haveCols) . " want: "
            . implode(',', $wantedCols)
            . "). Set MIG_REPLACE_PK=1 to force.");
    }
    return MIG_SKIPPED;
}

function assert_no_errno(DatabaseService $db, string $sql): void
{
    $errno = $db->getLastErrno();
    if ($errno > 0) {
        throw new RuntimeException("DB error ($errno): " . $db->getLastError() . "\n$sql");
    }
}

/** Return ordered primary-key columns for a table (lowercased, no backticks). */
function table_primary_key(DatabaseService $db, string $table): array
{
    $sql = "SELECT k.COLUMN_NAME
                FROM information_schema.TABLE_CONSTRAINTS t
                JOIN information_schema.KEY_COLUMN_USAGE k
                    ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                AND t.TABLE_SCHEMA = k.TABLE_SCHEMA
                AND t.TABLE_NAME   = k.TABLE_NAME
                WHERE t.TABLE_SCHEMA = ?
                AND t.TABLE_NAME   = ?
                AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
                ORDER BY k.ORDINAL_POSITION";
    $rows = $db->rawQuery($sql, [current_db($db), $table]) ?? [];
    if (!$rows) {
        return [];
    }
    return array_map(static fn($r) => strtolower(trim($r['COLUMN_NAME'] ?? '')), $rows);
}

/** Parse a column list like "`a`,`b`" into ['a','b'] (normalized). */
function parse_cols_list(string $list): array
{
    $parts = preg_split('/\s*,\s*/', trim($list));
    return array_map(static function ($c) {
        $c = trim($c, " \t\r\n`");
        // drop optional length like (10) or (10,2)
        $c = preg_replace('/\s*\(\s*\d+(?:\s*,\s*\d+)?\s*\)\s*/', '', $c);
        // drop ASC/DESC if present
        $c = preg_replace('/\s+(ASC|DESC)\b/i', '', $c);
        return strtolower($c);
    }, $parts);
}

/** Add column only if absent (portable across MySQL 5.x/8.x). */
function add_column_if_missing(DatabaseService $db, string $table, string $column, string $ddl): int
{
    $dbName = current_db($db);
    $exists = (int) ($db->rawQueryOne(
        "SELECT COUNT(*) c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?",
        [$dbName, $table, $column]
    )['c'] ?? 0);

    if ($exists === 0) {
        // Schema drifts across installs: if the column is positioned `AFTER <anchor>`
        // and that anchor column doesn't exist here, drop the AFTER clause so the ADD
        // still lands (column order is cosmetic). Prevents a 1054 "Unknown column" on
        // `AFTER <missing>` — the failure mode when an earlier migration that should
        // have created the anchor never landed on this install.
        if (preg_match('/\bafter\s+`?([a-z0-9_]+)`?\s*;?\s*$/i', $ddl, $am) && !column_exists($db, $table, $am[1])) {
            $ddl = preg_replace('/\s+after\s+`?[a-z0-9_]+`?(\s*;?\s*)$/i', '$1', $ddl);
        }
        $db->rawQuery($ddl);
        assert_no_errno($db, $ddl);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Does an index exist on table (by name)? */
function index_exists(DatabaseService $db, string $table, string $index): bool
{
    $dbName = current_db($db);
    $row = $db->rawQueryOne(
        "SELECT 1 AS ok FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1",
        [$dbName, $table, $index]
    );
    return (bool) $row;
}

/** Create index only if missing (works on MySQL 5.x/8.x and MariaDB). */
function add_index_if_missing(DatabaseService $db, string $table, string $index, string $ddl): int
{
    if (!index_exists($db, $table, $index)) {
        $db->rawQuery($ddl);
        assert_no_errno($db, $ddl);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/** Column exists? */
function column_exists(DatabaseService $db, string $table, string $column): bool
{
    $dbName = current_db($db);
    $row = $db->rawQueryOne(
        "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1",
        [$dbName, $table, $column]
    );
    return (bool) $row;
}

/** Foreign-key constraint exists (by name)? */
function foreign_key_exists(DatabaseService $db, string $table, string $constraint): bool
{
    $row = $db->rawQueryOne(
        "SELECT 1 AS ok FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=?
            AND CONSTRAINT_TYPE='FOREIGN KEY' LIMIT 1",
        [current_db($db), $table, $constraint]
    );
    return (bool) $row;
}

/**
 * DROP COLUMN only if present. MySQL refuses the drop (errno 1072) while any
 * index or foreign key still references the column, and hand-rolled migrations
 * don't always drop those first. Sweep referencing FKs and non-PRIMARY indexes
 * before the drop so it always succeeds.
 */
function drop_column_if_exists(DatabaseService $db, string $table, string $column): int
{
    if (!column_exists($db, $table, $column)) {
        return MIG_SKIPPED;
    }

    $dbName = current_db($db);

    // 1. Drop FK constraints on the SOURCE side that include this column.
    //    (FKs where this column is the referenced parent are left alone —
    //    auto-dropping those would silently break integrity elsewhere.)
    $fks = $db->rawQuery(
        "SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
            AND REFERENCED_TABLE_NAME IS NOT NULL",
        [$dbName, $table, $column]
    ) ?? [];
    foreach ($fks as $fk) {
        $sql = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);
    }

    // 2. Drop non-PRIMARY indexes that include this column. PRIMARY is left
    //    alone so we don't silently remove the table's primary key.
    $idx = $db->rawQuery(
        "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
            AND INDEX_NAME != 'PRIMARY'",
        [$dbName, $table, $column]
    ) ?? [];
    foreach ($idx as $i) {
        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$i['INDEX_NAME']}`";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);
    }

    // 3. Now the column drop will succeed.
    $sql = "ALTER TABLE `{$table}` DROP `{$column}`";
    $db->rawQuery($sql);
    assert_no_errno($db, $sql);
    return MIG_EXECUTED;
}

/** DROP FOREIGN KEY only if the constraint is present. */
function drop_foreign_key_if_exists(DatabaseService $db, string $table, string $constraint): int
{
    if (!foreign_key_exists($db, $table, $constraint)) {
        return MIG_SKIPPED;
    }
    $sql = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`";
    $db->rawQuery($sql);
    assert_no_errno($db, $sql);
    return MIG_EXECUTED;
}

/** DROP INDEX only if present */
function drop_index_if_exists(DatabaseService $db, string $table, string $index): int
{
    if (index_exists($db, $table, $index)) {
        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$index}`";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
}

/**
 * ALTER TABLE ... CHANGE [COLUMN] `old` `new` <coldef> — rename (and optional retype).
 * Idempotency hinges on which column is present:
 *   - `old` present -> run it. Either the rename hasn't applied yet, or
 *     old===new (a pure retype with no rename), harmless to re-run since
 *     CHANGE to the same definition is a no-op in MySQL.
 *   - `old` absent, `new` present -> already applied; skip cleanly (the
 *     retried-partial-deploy case).
 *   - neither present -> fall through to the raw path so a 1054 surfaces;
 *     we can't safely guess intent when both ends are missing.
 */
function _apply_change_column(DatabaseService $db, string $table, string $oldCol, string $newCol, string $ddl): int
{
    if (column_exists($db, $table, $oldCol)) {
        $db->rawQuery($ddl);
        assert_no_errno($db, $ddl);
        return MIG_EXECUTED;
    }
    if (strcasecmp($oldCol, $newCol) !== 0 && column_exists($db, $table, $newCol)) {
        return MIG_SKIPPED;
    }
    return MIG_NOT_HANDLED;
}

/**
 * Route known DDL patterns through idempotent helpers.
 * Returns true if handled (do not execute again), false to execute raw.
 */
function handle_idempotent_ddl(DatabaseService $db, SymfonyStyle $io, string $query): int
{
    $q = trim($query);
    $q = preg_replace('/NULL\s*AFTER/i', 'NULL AFTER', $q);

    // ALTER TABLE ... ADD [COLUMN] `col` ...
    // Guard: the bare-ADD form below also matches ADD INDEX/KEY/PRIMARY/etc.,
    // capturing the keyword as a "column name". Skip those so they fall through
    // to their dedicated routes instead of being mis-parsed as a column add.
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+/i', (string) $q, $m)) {
        $kw = strtolower($m[2]);
        if (!in_array($kw, ['index', 'key', 'primary', 'constraint', 'unique', 'fulltext', 'spatial', 'foreign'], true)) {
            return add_column_if_missing($db, $m[1], $m[2], $q);
        }
    }

    // CREATE [UNIQUE] INDEX idx ON table (...)
    if (preg_match('/^create\s+(unique\s+)?index\s+`?([^`]+)`?\s*(?:using\s+btree)?\s+on\s+`?([^`]+)`?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', (string) $q, $m)) {
        return add_index_if_missing($db, $m[3], $m[2], $q);
    }

    // ALTER TABLE ... ADD [UNIQUE] INDEX idx (...)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?index\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', (string) $q, $m)) {
        $table = $m[1];
        $uniqueKw = empty($m[2]) ? '' : 'UNIQUE ';
        $index = $m[3];
        $cols = trim($m[4]);
        $ddl = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        return add_index_if_missing($db, $table, $index, $ddl);
    }

    // ALTER TABLE ... ADD [UNIQUE] KEY idx (...) (synonym)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?key\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', (string) $q, $m)) {
        $table = $m[1];
        $uniqueKw = empty($m[2]) ? '' : 'UNIQUE ';
        $index = $m[3];
        $cols = trim($m[4]);
        $ddl = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        return add_index_if_missing($db, $table, $index, $ddl);
    }

    // ALTER TABLE ... ADD CONSTRAINT `name` FOREIGN KEY (...) — skip if the
    // constraint already exists. Re-adding raises errno 121, which bit a
    // retried partial deploy (columns + FK added, run retried, FK re-created).
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+constraint\s+`?([a-z0-9_]+)`?\s+foreign\s+key\b/i', (string) $q, $m)) {
        if (foreign_key_exists($db, $m[1], $m[2])) {
            return MIG_SKIPPED;
        }
        $db->rawQuery($q);
        assert_no_errno($db, $q);
        return MIG_EXECUTED;
    }

    // ALTER TABLE ... CHANGE [COLUMN] `old` `new` <coldef> — rename (and optional retype).
    // MODIFY is intentionally NOT routed here: it never renames, so re-running it is
    // already a harmless no-op at the raw-exec path.
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+change\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+`?([a-z0-9_]+)`?\b/i', (string) $q, $m)) {
        return _apply_change_column($db, $m[1], $m[2], $m[3], $q);
    }

    // ALTER TABLE ... RENAME COLUMN `old` TO `new` (MySQL 8.0+ syntax). Idempotent:
    // if the rename already applied (old gone, new present), skip. Otherwise fall
    // through to raw exec so the rename runs (or surfaces a real error).
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+rename\s+column\s+`?([a-z0-9_]+)`?\s+to\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        if (!column_exists($db, $m[1], $m[2]) && column_exists($db, $m[1], $m[3])) {
            return MIG_SKIPPED;
        }
    }

    // ALTER TABLE ... DROP FOREIGN KEY `fk` (must precede the DROP shorthand,
    // which would otherwise capture "foreign" as a column name and no-op).
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+foreign\s+key\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        return drop_foreign_key_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... DROP COLUMN `col`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+column\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        return drop_column_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... DROP `col` (shorthand). Guard against DROP {PRIMARY|KEY|
    // INDEX|CONSTRAINT|...} being mis-read as a column drop — those fall
    // through to their own routes or raw exec.
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        $kw = strtolower($m[2]);
        if (!in_array($kw, ['index', 'key', 'primary', 'constraint', 'unique', 'fulltext', 'spatial', 'foreign', 'check'], true)) {
            return drop_column_if_exists($db, $m[1], $m[2]);
        }
    }

    // ALTER TABLE ... DROP INDEX `idx`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+index\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        return drop_index_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... ADD PRIMARY KEY [USING BTREE] (...) [USING BTREE]
    if (preg_match('/^alter\s+table\s+`?([^`]+)`?\s+add\s+primary\s+key\s*(?:using\s+btree)?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', (string) $q, $m)) {
        return _apply_add_primary_key($db, $io, $m[1], $m[2], $q);
    }

    // ALTER TABLE ... ADD CONSTRAINT `name` PRIMARY KEY [USING BTREE] (...) [USING BTREE]
    if (preg_match('/^alter\s+table\s+`?([^`]+)`?\s+add\s+constraint\s+`?([^`]+)`?\s+primary\s+key\s*(?:using\s+btree)?\s*\((.+?)\)\s*(?:using\s+btree)?\s*;?$/is', (string) $q, $m)) {
        return _apply_add_primary_key($db, $io, $m[1], $m[3], $q);
    }

    return MIG_NOT_HANDLED;
}

/* ---------------------- End helpers ---------------------- */

$db->where('name', 'sc_version');
$currentVersion = $db->getValue('system_config', 'value');
$migrationFiles = (array) glob(ROOT_PATH . '/sys/migrations/*.sql');

// Extract version numbers and map them to files
$versions = array_map(fn($file): string => basename((string) $file, '.sql'), $migrationFiles);

// Sort versions
usort($versions, 'version_compare');

$options = getopt("yq", ["status", "dry-run", "version:"]);  // -y auto-continue on error, -q quiet, --status show pending, --dry-run preview, --version=X.Y.Z replay from version
$autoContinueOnError = isset($options['y']);
$quietMode = isset($options['q']);
$showStatus = isset($options['status']);
$dryRun = isset($options['dry-run']);
$fromVersion = $options['version'] ?? null;
$showProgress = !$quietMode && !$dryRun && !$showStatus;

if ($quietMode) {
    error_reporting(0);
}

$startVersion = $fromVersion ?? $currentVersion;
$pendingVersions = array_values(array_filter(
    $versions,
    fn($v) => version_compare($v, $startVersion, '>=')
));

if ($showStatus) {
    $io->writeln("Current DB version: " . ($currentVersion ?: '(none)'));
    if ($fromVersion !== null) {
        $io->writeln("Replay from:        $fromVersion");
    }
    $io->writeln("Pending migrations:");
    if (empty($pendingVersions)) {
        $io->writeln("  (none — database is up to date)");
    } else {
        foreach ($pendingVersions as $v) {
            $io->writeln("  $v.sql");
        }
    }
    exit(0);
}

$totalMigrations = 0;
$totalQueries = 0;
$skippedQueries = $successfulQueries = 0;
$totalErrors = 0;
$lastVersion = $currentVersion;

foreach ($versions as $version) {
    $file = ROOT_PATH . '/sys/migrations/' . $version . '.sql';

    if (version_compare($version, $startVersion, '>=')) {
        if (!$quietMode) {
            $io->section($dryRun ? "DRY RUN: version $version" : "Migrating to version $version");
        }
        $totalMigrations++;
        $versionErrors = 0;

        // Parse and pre-build statements, filtering out empties, so we know the total
        $sql_contents = file_get_contents($file);
        // Normalize SQL comments: "-- comment" requires a space after "--" per the
        // SQL standard, but migration files sometimes omit it (e.g. "--Insert ...").
        // Without the space the parser treats the line as a statement, causing errors.
        $sql_contents = preg_replace('/^(\s*--)(?=\S)/m', '$1 ', $sql_contents);
        $parser = new Parser($sql_contents);

        $builtStatements = [];
        foreach ($parser->statements as $statement) {
            $q = trim($statement->build() ?? '');
            if ($q !== '') {
                $builtStatements[] = $q;
            }
        }
        $versionTotal = count($builtStatements);
        $processedForVersion = 0;

        if ($dryRun) {
            foreach ($builtStatements as $query) {
                $totalQueries++;
                $preview = mb_substr(preg_replace('/\s+/', ' ', $query), 0, 100);
                $io->writeln("  WOULD RUN  $preview");
            }
            $lastVersion = $version;
            continue;
        }

        // New spinner/progress bar
        $bar = null;
        if ($showProgress && $versionTotal > 0) {
            $bar = MiscUtility::spinnerStart(
                $versionTotal,
                "Migrating $version …",
                '█',
                '░',
                '█',
                'cyan'
            );
        }

        $db->beginTransaction();
        $aborted = false;
        try {
            $db->rawQuery("SET FOREIGN_KEY_CHECKS = 0;");

            foreach ($builtStatements as $query) {
                try {
                    $totalQueries++;

                    $status = handle_idempotent_ddl($db, $io, $query);
                    if ($status === MIG_SKIPPED) {
                        $skippedQueries++;
                    } elseif ($status === MIG_EXECUTED) {
                        $successfulQueries++;
                    } else {
                        $db->rawQuery($query);
                        $errno = $db->getLastErrno();
                        if ($errno > 0) {
                            // Benign idempotence codes: 1050 table exists, 1060 dup column,
                            // 1061 dup key, 1068 multi PK, 1091 can't-drop-missing, 1826 dup FK.
                            // 1062 (duplicate entry) is benign only for seed-style INSERTs,
                            // which are re-runnable; elsewhere it's a real constraint violation.
                            $isInsertDupBenign = $errno === 1062 && strpos(strtolower(trim($query)), 'insert') === 0;
                            if (in_array($errno, [1050, 1060, 1061, 1068, 1091, 1826], true) || $isInsertDupBenign) {
                                if (!$quietMode && getenv('MIG_VERBOSE')) {
                                    if ($bar instanceof ProgressBar) {
                                        MiscUtility::spinnerPausePrint($bar, function () use ($db): void {
                                            echo "Benign idempotence (errno={$db->getLastErrno()}): {$db->getLastError()}\n{$db->getLastQuery()}\n";
                                        });
                                    } else {
                                        $io->comment("Benign idempotence (errno={$db->getLastErrno()}): {$db->getLastError()}\n{$db->getLastQuery()}\n");
                                    }
                                }
                                $skippedQueries++;
                            } else {
                                $totalErrors++;
                                $versionErrors++;
                                $msg = "Error executing query ({$errno}): {$db->getLastError()}\n{$db->getLastQuery()}\n";
                                if (!$quietMode) {
                                    if ($bar instanceof ProgressBar) {
                                        MiscUtility::spinnerPausePrint($bar, fn() => print $msg);
                                    } else {
                                        $io->error($msg);
                                    }
                                    if ($canLog) {
                                        LoggerUtility::logError($msg);
                                    }
                                }
                                if (!$autoContinueOnError) {
                                    if ($bar instanceof ProgressBar) {
                                        MiscUtility::spinnerPausePrint($bar, fn() => print "Do you want to continue? (y/n): ");
                                    } else {
                                        echo "Do you want to continue? (y/n): ";
                                    }
                                    $handle = fopen("php://stdin", "r");
                                    $response = trim(fgets($handle));
                                    fclose($handle);
                                    if (strtolower($response) !== 'y') {
                                        $aborted = true;
                                        throw new RuntimeException("Migration aborted by user.");
                                    }
                                }
                            }
                        } else {
                            $successfulQueries++;
                        }
                    }
                } catch (Throwable $e) {
                    $msgStr = $e->getMessage() ?? '';
                    $sqlInMsg = $query ?? '';

                    $isBenign =
                        stripos($msgStr, 'Duplicate column name') !== false ||
                        stripos($msgStr, 'Duplicate key name') !== false ||
                        (stripos($msgStr, 'already exists') !== false && str_contains($msgStr, '1050')) ||
                        (stripos($msgStr, "Can't DROP") !== false && stripos($msgStr, 'check that column/key exists') !== false) ||
                        stripos($msgStr, 'Multiple primary key defined') !== false || str_contains($msgStr, '1068') ||
                        stripos($msgStr, 'Duplicate foreign key') !== false || str_contains($msgStr, '1826') ||
                        (str_contains($msgStr, '1062') || stripos($msgStr, 'Duplicate entry') !== false)
                            && strpos(strtolower(trim($query)), 'insert') === 0;

                    if ($isBenign) {
                        if (!$quietMode && getenv('MIG_VERBOSE')) {
                            $toPrint = "Benign idempotence (exception):\n{$sqlInMsg}\n{$msgStr}\n";
                            if ($bar instanceof ProgressBar) {
                                MiscUtility::spinnerPausePrint($bar, fn() => print $toPrint);
                            } else {
                                echo $toPrint;
                            }
                            if ($canLog) {
                                LoggerUtility::log('info', $toPrint);
                            }
                        }
                        $skippedQueries++;
                    } else {
                        $totalErrors++;
                        $versionErrors++;
                        if (!$quietMode) {
                            $toPrint = "";
                            if ($bar instanceof ProgressBar) {
                                MiscUtility::spinnerPausePrint($bar, fn() => print $toPrint);
                            } else {
                                echo $toPrint;
                            }
                            if ($canLog) {
                                LoggerUtility::logError($toPrint);
                            }
                        }
                        if (!$autoContinueOnError) {
                            if ($bar instanceof ProgressBar) {
                                MiscUtility::spinnerPausePrint($bar, fn() => print "Do you want to continue? (y/n): ");
                            } else {
                                echo "Do you want to continue? (y/n): ";
                            }
                            $handle = fopen("php://stdin", "r");
                            $response = trim(fgets($handle));
                            fclose($handle);
                            if (strtolower($response) !== 'y') {
                                $aborted = true;
                                throw new RuntimeException("Migration aborted by user.", $e->getCode(), $e);
                            }
                        }
                    }
                } finally {
                    $processedForVersion++;
                    if ($bar instanceof ProgressBar) {
                        // Update message with step info and advance
                        MiscUtility::spinnerUpdate($bar, "v{$version}", null, $processedForVersion, $versionTotal);
                        MiscUtility::spinnerAdvance($bar, 1);
                    }
                }
            }

            if (!$quietMode) {
                if ($bar instanceof ProgressBar) {
                    MiscUtility::spinnerFinish($bar);
                }
                $io->newLine();
                $io->success("Migration to version $version completed.");
            }
        } finally {
            $db->rawQuery("SET FOREIGN_KEY_CHECKS = 1;");
            if ($aborted) {
                $db->rollbackTransaction();
                if ($bar instanceof ProgressBar) {
                    MiscUtility::spinnerFinish($bar);
                }
                exit("Migration aborted by user.\n");
            }

            // Persist the version only if the run wasn't aborted AND no non-benign
            // errors occurred. Previously the version was bumped unconditionally, so a
            // migration with a failed/skipped DDL marked itself "done" while silently
            // missing schema (the silent-drift bug). Commit either way so the work that
            // did succeed lands, but only advance sc_version on a clean run.
            $shouldBumpVersion = $versionErrors === 0;
            if ($shouldBumpVersion) {
                $db->where('name', 'sc_version');
                $db->update('system_config', ['value' => $version]);
                $lastVersion = $version;
            } elseif (!$quietMode) {
                $io->warning("app_version NOT bumped to $version: $versionErrors non-benign error(s) occurred. Fix the issue(s) above and re-run migrate.php.");
            }

            $db->commitTransaction();
        }

        // Halt the migration chain on unresolved errors: downstream migrations often
        // assume prior versions applied cleanly, so continuing risks cascading damage.
        if ($versionErrors > 0) {
            if (!$quietMode) {
                $io->warning("Halting further migrations after $version due to unresolved errors.");
            }
            break;
        }
    }

    gc_collect_cycles();
}

if (!$quietMode) {
    $summaryRows = [];
    if ($dryRun) {
        $summaryRows[] = ['Mode', 'DRY RUN (no changes made)'];
    }
    $summaryRows[] = ['DB version', ($currentVersion ?: '(none)') . '  ->  ' . ($lastVersion ?: '(none)')];
    $summaryRows[] = ['Migrations attempted', $totalMigrations];
    $summaryRows[] = ['Queries executed', $totalQueries];
    if (!$dryRun) {
        $summaryRows[] = ['Successful queries', $successfulQueries];
        $summaryRows[] = ['Skipped queries', $skippedQueries];
        $summaryRows[] = ['Potential Errors logged', $totalErrors];
    }
    $io->table(['Migration summary', ''], $summaryRows);
}
