#!/usr/bin/env php
<?php

// bin/migrate.php

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

/** DROP COLUMN only if present */
function drop_column_if_exists(DatabaseService $db, string $table, string $column): int
{
    if (column_exists($db, $table, $column)) {
        $sql = "ALTER TABLE `{$table}` DROP `{$column}`";
        $db->rawQuery($sql);
        assert_no_errno($db, $sql);
        return MIG_EXECUTED;
    }
    return MIG_SKIPPED;
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
 * Route known DDL patterns through idempotent helpers.
 * Returns true if handled (do not execute again), false to execute raw.
 */
function handle_idempotent_ddl(DatabaseService $db, SymfonyStyle $io, string $query): int
{
    $q = trim($query);
    $q = preg_replace('/NULL\s*AFTER/i', 'NULL AFTER', $q);

    // ALTER TABLE ... ADD [COLUMN] `col` ...
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+/i', (string) $q, $m)) {
        return add_column_if_missing($db, $m[1], $m[2], $q);
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

    // ALTER TABLE ... DROP COLUMN `col`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+column\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        return drop_column_if_exists($db, $m[1], $m[2]);
    }

    // ALTER TABLE ... DROP `col` (shorthand)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+`?([a-z0-9_]+)`?/i', (string) $q, $m)) {
        return drop_column_if_exists($db, $m[1], $m[2]);
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

$options = getopt("yq");  // -y auto-continue on error, -q quiet
$autoContinueOnError = isset($options['y']);
$quietMode = isset($options['q']);
$showProgress = !$quietMode;

if ($quietMode) {
    error_reporting(0);
}

$totalMigrations = 0;
$totalQueries = 0;
$skippedQueries = $successfulQueries = 0;
$totalErrors = 0;

foreach ($versions as $version) {
    $file = ROOT_PATH . '/sys/migrations/' . $version . '.sql';

    if (version_compare($version, $currentVersion, '>=')) {
        if (!$quietMode) {
            $io->section("Migrating to version $version");
        }
        $totalMigrations++;

        // Parse and pre-build statements, filtering out empties, so we know the total
        $sql_contents = file_get_contents($file);
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
                            if (in_array($errno, [1060, 1061, 1068, 1091], true)) {
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
                        (stripos($msgStr, "Can't DROP") !== false && stripos($msgStr, 'check that column/key exists') !== false) ||
                        stripos($msgStr, 'Multiple primary key defined') !== false || str_contains($msgStr, '1068');

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

            // Persist the version only if the run wasn't aborted
            $db->where('name', 'sc_version');
            $db->update('system_config', ['value' => $version]);

            $db->commitTransaction();
        }
    }

    gc_collect_cycles();
}

if (!$quietMode) {
    $io->table(
        ['Migration summary', ''],
        [
            ['Migrations attempted', $totalMigrations],
            ['Queries executed', $totalQueries],
            ['Successful queries', $successfulQueries],
            ['Skipped queries', $skippedQueries],
            ['Potential Errors logged', $totalErrors],
        ]
    );
}
