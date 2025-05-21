#!/usr/bin/env php
<?php

/**
 * This script converts the database tables and columns to use the utf8mb4 character set.
 * It ensures compatibility with emojis and other special characters.
 * Optimized for performance on large tables by only converting what needs to be converted.
 *
 * Note: This script should only be run from the command line.
 */

if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once(__DIR__ . '/../bootstrap.php');

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Parse command line arguments
$options = getopt('dbts:', ['dry-run', 'batch-size:', 'table:', 'skip-columns']);
$dryRun = isset($options['dry-run']) || isset($options['d']);
$batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : (isset($options['b']) ? (int)$options['b'] : 10);
$specificTable = isset($options['table']) ? $options['table'] : (isset($options['t']) ? $options['t'] : null);
$skipColumnConversion = isset($options['skip-columns']) || isset($options['s']);

echo "Mode: " . ($dryRun ? "Dry Run (no changes will be made)" : "Live Run") . PHP_EOL;
echo "Batch Size: $batchSize tables at a time" . PHP_EOL;
if ($specificTable) {
    echo "Processing specific table: $specificTable" . PHP_EOL;
}
if ($skipColumnConversion) {
    echo "Skipping individual column conversion (only converting tables)" . PHP_EOL;
}

$dbName = SYSTEM_CONFIG['database']['db'];
$interfaceDbConfig = null;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if (!isset(SYSTEM_CONFIG['interfacing']['enabled']) || SYSTEM_CONFIG['interfacing']['enabled'] !== false) {
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
    $interfaceDbConfig = SYSTEM_CONFIG['interfacing']['database'] ?? null;
}

/**
 * Check if a table needs conversion based on its current charset and collation
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param string $targetCollation
 * @return bool
 */
function tableNeedsConversion(DatabaseService $db, string $connectionName, string $tableName, string $targetCollation): bool
{
    $tableStatus = $db->connection($connectionName)->rawQuery("SHOW TABLE STATUS LIKE '$tableName'");

    if (empty($tableStatus)) {
        return false;
    }

    $table = $tableStatus[0];
    return ($table['Collation'] !== $targetCollation);
}

/**
 * Get columns that need conversion in a table
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param string $targetCollation
 * @return array
 */
function getColumnsNeedingConversion(DatabaseService $db, string $connectionName, string $tableName, string $targetCollation): array
{
    $needConversion = [];
    $columns = $db->connection($connectionName)->rawQuery("SHOW FULL COLUMNS FROM `$tableName`");

    foreach ($columns as $column) {
        if (preg_match('/char|varchar|text|tinytext|mediumtext|longtext|enum|set/i', $column['Type']) &&
            $column['Collation'] !== null &&
            $column['Collation'] !== $targetCollation) {
            $needConversion[] = $column;
        }
    }

    return $needConversion;
}

/**
 * Converts a table and only the necessary columns to utf8mb4 character set.
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param bool $dryRun
 * @param bool $skipColumnConversion
 * @throws Exception
 */
function convertTableAndColumns(DatabaseService $db, string $connectionName, string $tableName, bool $dryRun = false, bool $skipColumnConversion = false): void
{
    $collation = $db->isMySQL8OrHigher() ? 'utf8mb4_0900_ai_ci' : 'utf8mb4_unicode_ci';

    // Check if table needs conversion
    $tableNeedsConversion = tableNeedsConversion($db, $connectionName, $tableName, $collation);

    // Get table size information (simple query)
    $tableSizeInfo = $db->connection($connectionName)->rawQuery("SELECT
        ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size'
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = '$tableName'");

    $tableSize = !empty($tableSizeInfo) ? $tableSizeInfo[0]['Size'] : 'unknown';

    if (!$tableNeedsConversion) {
        echo "✓ Table $tableName ($tableSize MB) already uses $collation - skipping table conversion" . PHP_EOL;
    } else {
        echo PHP_EOL . "⚙ Converting table: $tableName ($tableSize MB)" . PHP_EOL;

        if (!$dryRun) {
            try {
                $startTime = microtime(true);
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE $collation");
                $duration = round(microtime(true) - $startTime, 2);
                echo "✓ Table converted successfully in $duration seconds" . PHP_EOL;
            } catch (Throwable $e) {
                echo "❌ Failed to convert table '$tableName' due to error: " . $e->getMessage() . PHP_EOL;
                LoggerUtility::logError("Failed to convert table $tableName", [
                    'table' => $tableName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);
                return; // Skip column conversion if table conversion failed
            }
        } else {
            echo "🔍 DRY RUN: Would convert table structure to utf8mb4 with $collation" . PHP_EOL;
        }
    }

    if ($skipColumnConversion) {
        echo "⏩ Skipping individual column conversion as requested" . PHP_EOL;
        return;
    }

    // Only get columns that need conversion
    $columnsNeedingConversion = getColumnsNeedingConversion($db, $connectionName, $tableName, $collation);

    if (empty($columnsNeedingConversion)) {
        echo "✓ All columns in $tableName already use correct collation" . PHP_EOL;
        return;
    }

    echo "⚙ Found " . count($columnsNeedingConversion) . " columns needing conversion in $tableName" . PHP_EOL;

    if (!$dryRun) {
        foreach ($columnsNeedingConversion as $column) {
            try {
                $null = $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                $default = $column['Default'] !== null ? "DEFAULT '" . $db->connection($connectionName)->escape($column['Default']) . "'" : '';
                $extra = $column['Extra'] ?? '';

                echo "  ⚙ Converting column: {$column['Field']} (current collation: {$column['Collation']})" . PHP_EOL;
                $startTime = microtime(true);

                $columnDefinition = "`{$column['Field']}` {$column['Type']} CHARACTER SET utf8mb4 COLLATE $collation $null $default $extra";
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` MODIFY $columnDefinition");

                $duration = round(microtime(true) - $startTime, 2);
                echo "  ✓ Column {$column['Field']} converted in $duration seconds" . PHP_EOL;
            } catch (Throwable $e) {
                echo "  ❌ Failed to convert column '{$column['Field']}' in table '$tableName' due to error: " . $e->getMessage() . PHP_EOL;
                LoggerUtility::logError("Failed to convert column {$column['Field']} in table $tableName", [
                    'column' => $column['Field'],
                    'table' => $tableName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    } else {
        foreach ($columnsNeedingConversion as $column) {
            echo "  🔍 DRY RUN: Would convert column '{$column['Field']}' from {$column['Collation']} to $collation" . PHP_EOL;
        }
    }
}

/**
 * Retrieves a list of tables from a given database.
 *
 * @param DatabaseService $db
 * @param string $schema
 * @param string $connectionName
 * @param string|null $specificTable
 * @return array
 * @throws Exception
 */
function fetchTables(DatabaseService $db, string $schema, string $connectionName, ?string $specificTable = null): array
{
    // First, get all tables without the size information
    $query = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = '$schema'";

    if ($specificTable) {
        $query .= " AND TABLE_NAME = '$specificTable'";
    }

    $tables = $db->connection($connectionName)->rawQuery($query);

    if (!$tables) {
        if ($specificTable) {
            throw new Exception("Table '$specificTable' not found in the database $schema (connection: $connectionName).");
        } else {
            throw new Exception("No tables found in the database $schema (connection: $connectionName).");
        }
    }

    // Return just the table names
    return array_map(fn($table) => $table['TABLE_NAME'] ?? null, $tables);
}

/**
 * Process tables in batches to prevent memory issues
 *
 * @param array $tables
 * @param int $batchSize
 * @param callable $processFunction
 */
function processBatches(array $tables, int $batchSize, callable $processFunction): void
{
    $totalTables = count($tables);
    $batches = ceil($totalTables / $batchSize);

    echo "Processing $totalTables tables in $batches batches of up to $batchSize tables each" . PHP_EOL;

    for ($i = 0; $i < $totalTables; $i += $batchSize) {
        $batchTables = array_slice($tables, $i, $batchSize);
        $batchNumber = floor($i / $batchSize) + 1;

        echo PHP_EOL . "Starting batch $batchNumber of $batches..." . PHP_EOL;
        $startTime = microtime(true);

        foreach ($batchTables as $index => $tableData) {
            $processFunction($tableData, $i + $index + 1, $totalTables);
        }

        $duration = round(microtime(true) - $startTime, 2);
        echo PHP_EOL . "Completed batch $batchNumber in $duration seconds" . PHP_EOL;

        // Force garbage collection between batches
        if ($batches > 1) {
            echo "Cleaning up memory between batches..." . PHP_EOL;
            gc_collect_cycles();
        }
    }
}

try {
    // If specific table provided, only process that one
    if ($specificTable) {
        try {
            $tablesList = fetchTables($db, $dbName, 'default', $specificTable);
            $allTables = array_map(fn($table) => ['table' => $table, 'connection' => 'default'], $tablesList);
        } catch (Exception $e) {
            // If not found in default DB, try interface DB
            if ($interfaceDbConfig) {
                $interfaceDbName = $interfaceDbConfig['db'] ?? null;
                if ($interfaceDbName) {
                    $tablesList = fetchTables($db, $interfaceDbName, 'interface', $specificTable);
                    $allTables = array_map(fn($table) => ['table' => $table, 'connection' => 'interface'], $tablesList);
                }
            } else {
                throw $e;
            }
        }
    } else {
        // Fetch the list of tables from the primary database
        $tablesList = fetchTables($db, $dbName, 'default');

        // Fetch the list of tables from the interfacing database if configured
        $interfaceTablesList = [];
        if ($interfaceDbConfig) {
            $interfaceDbName = $interfaceDbConfig['db'] ?? null;
            if ($interfaceDbName) {
                $interfaceTablesList = fetchTables($db, $interfaceDbName, 'interface');
            }
        }

        if (empty($tablesList) && empty($interfaceTablesList)) {
            throw new Exception("No tables found for conversion.");
        }

        // Merge tables and include connection info
        $allTables = array_merge(
            array_map(fn($table) => ['table' => $table, 'connection' => 'default'], $tablesList),
            array_map(fn($table) => ['table' => $table, 'connection' => 'interface'], $interfaceTablesList)
        );
    }

    $totalTables = count($allTables);
    echo "Starting conversion process for $totalTables tables..." . PHP_EOL;

    // Start timer
    $scriptStartTime = microtime(true);

    // Process tables in batches
    processBatches($allTables, $batchSize, function($tableData, $current, $total) use ($db, $dryRun, $skipColumnConversion) {
        echo PHP_EOL . "Table $current of $total: " . PHP_EOL;
        convertTableAndColumns($db, $tableData['connection'], $tableData['table'], $dryRun, $skipColumnConversion);
    });

    $totalDuration = round(microtime(true) - $scriptStartTime, 2);
    echo PHP_EOL . "Conversion process completed successfully in $totalDuration seconds." . PHP_EOL;

} catch (Throwable $e) {
    echo PHP_EOL . "An error occurred during the conversion process:" . $e->getFile() . ":" . $e->getLine() . " = " . $e->getMessage()  . PHP_EOL;
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
