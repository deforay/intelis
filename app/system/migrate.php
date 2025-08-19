#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../../bootstrap.php";

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$currentMajorVersion = $general->getAppVersion();

// Ensure the script only runs for VLSM APP VERSION >= 4.4.3
if (version_compare($currentMajorVersion, '4.4.3', '<')) {
    exit("This script requires VERSION 4.4.3 or higher. Current version: " . htmlspecialchars($currentMajorVersion) . "\n");
}

// Define the logs directory path
$logsDir = ROOT_PATH . "/logs";

// Initialize a flag to determine if logging is possible
$canLog = false;

// Check if the directory exists
if (!file_exists($logsDir)) {
    if (!MiscUtility::makeDirectory($logsDir)) {
        echo "Failed to create directory: $logsDir\n";
    } else {
        echo "Directory created: $logsDir\n";
        $canLog = file_exists($logsDir) && is_writable($logsDir);
    }
} else {
    $canLog = file_exists($logsDir) && is_writable($logsDir);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Check if connection was successful
if ($db->isConnected() === false) {
    exit("Database connection failed. Please check your database settings\n");
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

/** Add column only if absent (portable across MySQL 5.x/8.x). */
function add_column_if_missing(DatabaseService $db, string $table, string $column, string $ddl): void
{
    $dbName = current_db($db);
    $exists = (int)($db->rawQueryOne(
        "SELECT COUNT(*) c
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?",
        [$dbName, $table, $column]
    )['c'] ?? 0);

    if ($exists === 0) {
        $db->rawQuery($ddl);
    }
}

/** Does an index exist on table (by name)? */
function index_exists(DatabaseService $db, string $table, string $index): bool
{
    $dbName = current_db($db);
    $row = $db->rawQueryOne(
        "SELECT 1 AS ok
           FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1",
        [$dbName, $table, $index]
    );
    return (bool)$row;
}

/** Create index only if missing (works on MySQL 5.x/8.x and MariaDB). */
function add_index_if_missing(DatabaseService $db, string $table, string $index, string $ddl): void
{
    if (!index_exists($db, $table, $index)) {
        $db->rawQuery($ddl);
    }
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
    return (bool)$row;
}

/** DROP COLUMN only if present */
function drop_column_if_exists(DatabaseService $db, string $table, string $column): void
{
    if (column_exists($db, $table, $column)) {
        $db->rawQuery("ALTER TABLE `{$table}` DROP `{$column}`");
    }
}

/** DROP INDEX only if present */
function drop_index_if_exists(DatabaseService $db, string $table, string $index): void
{
    if (index_exists($db, $table, $index)) {
        $db->rawQuery("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }
}

/**
 * Route known DDL patterns through idempotent helpers.
 * Returns true if handled (do not execute again), false to execute raw.
 */
function handle_idempotent_ddl(DatabaseService $db, string $query): bool
{
    $q = trim($query);
    // normalize occasional collapsed whitespace after parser->build()
    $q = preg_replace('/NULL\s*AFTER/i', 'NULL AFTER', $q);

    // ALTER TABLE ... ADD [COLUMN] `col` ...
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(?:column\s+)?`?([a-z0-9_]+)`?\s+/i', $q, $m)) {
        add_column_if_missing($db, $m[1], $m[2], $q);
        return true;
    }

    // CREATE [UNIQUE] INDEX idx ON table (...)
    if (preg_match('/^create\s+(unique\s+)?index\s+`?([a-z0-9_]+)`?\s+on\s+`?([a-z0-9_]+)`?\s*\(/i', $q, $m)) {
        add_index_if_missing($db, $m[3], $m[2], $q);
        return true;
    }

    // ALTER TABLE ... ADD [UNIQUE] INDEX idx (...)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?index\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', $q, $m)) {
        $table = $m[1];
        $uniqueKw = !empty($m[2]) ? 'UNIQUE ' : '';
        $index = $m[3];
        $cols  = trim($m[4]);
        $ddl   = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        add_index_if_missing($db, $table, $index, $ddl);
        return true;
    }

    // ALTER TABLE ... ADD [UNIQUE] KEY idx (...) (synonym for INDEX)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+add\s+(unique\s+)?key\s+`?([a-z0-9_]+)`?\s*\((.+)\)\s*;?$/is', $q, $m)) {
        $table = $m[1];
        $uniqueKw = !empty($m[2]) ? 'UNIQUE ' : '';
        $index = $m[3];
        $cols  = trim($m[4]);
        $ddl   = sprintf('CREATE %sINDEX `%s` ON `%s` (%s)', $uniqueKw, $index, $table, $cols);
        add_index_if_missing($db, $table, $index, $ddl);
        return true;
    }

    // ALTER TABLE ... DROP COLUMN `col`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+column\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        drop_column_if_exists($db, $m[1], $m[2]);
        return true;
    }

    // ALTER TABLE ... DROP `col` (shorthand without COLUMN)
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        drop_column_if_exists($db, $m[1], $m[2]);
        return true;
    }

    // ALTER TABLE ... DROP INDEX `idx`
    if (preg_match('/^alter\s+table\s+`?([a-z0-9_]+)`?\s+drop\s+index\s+`?([a-z0-9_]+)`?/i', $q, $m)) {
        drop_index_if_exists($db, $m[1], $m[2]);
        return true;
    }

    return false;
}

/* ---------------------- End helpers ---------------------- */

$db->where('name', 'sc_version');
$currentVersion = $db->getValue('system_config', 'value');
$migrationFiles = glob(ROOT_PATH . '/dev/migrations/*.sql');

// Extract version numbers and map them to files
$versions = array_map(function ($file) {
    return basename($file, '.sql');
}, $migrationFiles);

// Sort versions
usort($versions, 'version_compare');

$options = getopt("yq");  // -y auto-continue on error, -q quiet
$autoContinueOnError = isset($options['y']);
$quietMode = isset($options['q']);

if ($quietMode) {
    error_reporting(0);
}
$totalMigrations = 0;
$totalQueries = 0;
$skippedQueries = $successfulQueries = 0;
$totalErrors     = 0;

foreach ($versions as $version) {
    $file = APPLICATION_PATH . '/../dev/migrations/' . $version . '.sql';

    if (version_compare($version, $currentVersion, '>=')) {
        echo "Migrating to version $version...\n";
        $totalMigrations++;

        $sql_contents = file_get_contents($file);
        $parser = new Parser($sql_contents);

        $db->beginTransaction();
        try {
            $db->rawQuery("SET FOREIGN_KEY_CHECKS = 0;");

            foreach ($parser->statements as $statement) {
                $query = null;
                try {
                    $query = trim($statement->build() ?? '');
                    if ($query === '') {
                        continue;
                    }

                    $totalQueries++;

                    if (handle_idempotent_ddl($db, $query)) {
                        // The helper decided this statement was already satisfied (idempotent)
                        $skippedQueries++;
                        $successfulQueries++;
                        continue;
                    }

                    $db->rawQuery($query);

                    // only check errno right after the call that can set it
                    $errno = $db->getLastErrno();
                    if ($errno > 0) {
                        // benign idempotence outcomes
                        if (in_array($errno, [
                            1060, /* dup column */
                            1061, /* dup key/index */
                            1091  /* can't drop: doesn't exist */
                        ], true)) {
                            if (!$quietMode && getenv('MIG_VERBOSE')) {
                                $msg = "Benign idempotence (errno=$errno): {$db->getLastError()}\n{$db->getLastQuery()}\n";
                                echo $msg;
                                if ($canLog) LoggerUtility::log('info', $msg);
                            }
                            $skippedQueries++;
                            $successfulQueries++;
                        } else {
                            // real error
                            $totalErrors++;
                            $msg = "Error executing query ({$errno}): {$db->getLastError()}\n{$db->getLastQuery()}\n";
                            if (!$quietMode) {
                                echo $msg;
                                if ($canLog) LoggerUtility::log('error', $msg);
                            }
                            if (!$autoContinueOnError) {
                                echo "Do you want to continue? (y/n): ";
                                $handle = fopen("php://stdin", "r");
                                $response = trim(fgets($handle));
                                fclose($handle);
                                if (strtolower($response) !== 'y') {
                                    throw new RuntimeException("Migration aborted by user.");
                                }
                            }
                        }
                    } else {
                        $successfulQueries++;
                    }
                } catch (Throwable $e) {
                    $msgStr  = $e->getMessage() ?? '';
                    $sqlInMsg = $query ?? '';

                    // heuristics for benign idempotence
                    $isBenign =
                        stripos($msgStr, 'Duplicate column name') !== false ||
                        stripos($msgStr, 'Duplicate key name') !== false   ||
                        (stripos($msgStr, "Can't DROP") !== false && stripos($msgStr, 'check that column/key exists') !== false);

                    if ($isBenign) {
                        if (!$quietMode && getenv('MIG_VERBOSE')) {
                            $msg = "Benign idempotence (exception):\n{$sqlInMsg}\n{$msgStr}\n";
                            echo $msg;
                            if ($canLog) LoggerUtility::log('info', $msg);
                        }
                        // do NOT increment $totalErrors
                    } else {
                        $totalErrors++;
                        if (!$quietMode) {
                            echo "An error occurred during migration. Please check the logs for details.\n";
                            $msg = "Exception while executing:\n{$sqlInMsg}\n{$msgStr}\n";
                            if ($canLog) LoggerUtility::log('error', $msg);
                        }
                        if (!$autoContinueOnError) {
                            echo "Do you want to continue? (y/n): ";
                            $handle = fopen("php://stdin", "r");
                            $response = trim(fgets($handle));
                            fclose($handle);
                            if (strtolower($response) !== 'y') {
                                throw new RuntimeException("Migration aborted by user.");
                            }
                        }
                    }
                }
            }

            echo "Migration to version $version completed.\n";
        } finally {
            // best-effort ensure FK checks are restored
            $db->rawQuery("SET FOREIGN_KEY_CHECKS = 1;");
            // commit if no fatal errors caused a throw
            try {
                $db->commitTransaction();
            } catch (Throwable $e) {
                // if commit fails, rollback
                $db->rollbackTransaction();
                throw $e;
            }
        }
    }

    gc_collect_cycles();
}
if (!$quietMode) {
    echo "\n=======================================\n";
    echo "Migration summary:\n";
    echo "  Migrations attempted : $totalMigrations\n";
    echo "  Queries executed     : $totalQueries\n";
    echo "  Successful queries   : $successfulQueries\n";
    echo "  Skipped queries      : $skippedQueries\n";
    echo "  Errors logged        : $totalErrors\n";
    echo "=======================================\n\n";
}
