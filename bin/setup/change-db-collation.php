#!/usr/bin/env php
<?php

/**
 * This script converts the database tables and columns to use the utf8mb4 character set.
 * It ensures compatibility with emojis and other special characters.
 * Optimized for performance on large tables by only converting what needs to be converted.
 * Preserves all column properties including constraints, defaults, comments, etc.
 *
 * Note: This script should only be run from the command line.
 */

if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once(__DIR__ . '/../../bootstrap.php');

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Helper\ProgressBar;

// Parse command line arguments
$options = getopt('dbtsv', ['dry-run', 'batch-size:', 'table:', 'skip-columns', 'verbose']);
$dryRun = isset($options['dry-run']) || isset($options['d']);
$batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : (isset($options['b']) ? (int)$options['b'] : 10);
$specificTable = $options['table'] ?? $options['t'] ?? null;
$skipColumnConversion = isset($options['skip-columns']) || isset($options['s']);
$verbose = isset($options['verbose']) || isset($options['v']);

// Collection of errors for summary at the end
$tableErrors = [];
$columnErrors = [];
$successfulTables = [];
$skippedTables = [];

// Terminal colors for better readability
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
    'white' => "\033[37m",
    'bold' => "\033[1m"
];

/**
 * Echo message with optional colorization and verbosity check
 *
 * @param string $message
 * @param string|null $color
 * @param bool $alwaysShow
 */
function echoMessage(string $message, ?string $color = null, bool $alwaysShow = false, ?ProgressBar $bar = null): void
{
    global $verbose, $colors;
    if (!$verbose && !$alwaysShow) {
        return;
    }

    $printer = function () use ($message, $color, $colors): void {
        if ($color && isset($colors[$color])) {
            echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
        } else {
            echo $message . PHP_EOL;
        }
    };

    if ($bar instanceof \Symfony\Component\Console\Helper\ProgressBar) {
        MiscUtility::spinnerPausePrint($bar, $printer);
    } else {
        $printer();
    }
}

if ($specificTable) {
    echoMessage("Processing specific table: $specificTable", 'bold', true);
}
if ($skipColumnConversion) {
    echoMessage("Skipping individual column conversion (only converting tables)", 'yellow', true);
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
 * @return array [bool $needsConversion, string $currentCollation]
 */
function tableNeedsConversion(DatabaseService $db, string $connectionName, string $tableName, string $targetCollation): array
{
    $tableStatus = $db->connection($connectionName)->rawQuery("SHOW TABLE STATUS LIKE '$tableName'");

    if (empty($tableStatus)) {
        return [false, 'unknown'];
    }

    $table = $tableStatus[0];
    $currentCollation = $table['Collation'];
    return [$currentCollation !== $targetCollation, $currentCollation];
}

/**
 * Get complete column information for columns that need conversion
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param string $targetCollation
 * @return array
 */
function getColumnsNeedingConversion(DatabaseService $db, string $connectionName, string $tableName, string $targetCollation): array
{
    $schema = $connectionName === 'default' ? $GLOBALS['dbName'] : ($GLOBALS['interfaceDbConfig']['db'] ?? $GLOBALS['dbName']);

    $query = "SELECT
                c.COLUMN_NAME,
                c.COLUMN_TYPE,
                c.IS_NULLABLE,
                c.COLUMN_DEFAULT,
                c.EXTRA,
                c.COLLATION_NAME,
                c.COLUMN_COMMENT,
                c.ORDINAL_POSITION
              FROM information_schema.columns c
              WHERE c.table_schema = '$schema'
              AND c.table_name = '$tableName'
              AND c.DATA_TYPE IN ('char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set')
              AND c.COLLATION_NAME IS NOT NULL
              AND c.COLLATION_NAME != '$targetCollation'
              ORDER BY c.ORDINAL_POSITION";

    return $db->connection($connectionName)->rawQuery($query);
}

/**
 * Get indexes that reference a specific column
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param string $columnName
 * @return array
 */
function getColumnIndexes(DatabaseService $db, string $connectionName, string $tableName, string $columnName): array
{
    $schema = $connectionName === 'default' ? $GLOBALS['dbName'] : ($GLOBALS['interfaceDbConfig']['db'] ?? $GLOBALS['dbName']);

    $query = "SELECT DISTINCT
                INDEX_NAME,
                NON_UNIQUE,
                INDEX_TYPE,
                COLUMN_NAME
              FROM information_schema.statistics
              WHERE table_schema = '$schema'
              AND table_name = '$tableName'
              AND column_name = '$columnName'
              AND INDEX_NAME != 'PRIMARY'";

    return $db->connection($connectionName)->rawQuery($query);
}

/**
 * Build proper column definition preserving all properties
 *
 * @param array $column Column information from information_schema
 * @param string $targetCollation Target collation
 * @return string Complete column definition
 */
function buildColumnDefinition(array $column, string $targetCollation): string
{
    $definition = "`{$column['COLUMN_NAME']}` {$column['COLUMN_TYPE']} CHARACTER SET utf8mb4 COLLATE $targetCollation";

    // Handle NULL/NOT NULL
    if ($column['IS_NULLABLE'] === 'NO') {
        $definition .= " NOT NULL";
    } else {
        $definition .= " NULL";
    }

    // Handle DEFAULT values with proper escaping and special cases
    if ($column['COLUMN_DEFAULT'] !== null) {
        $defaultValue = $column['COLUMN_DEFAULT'];

        // Special function defaults that don't need quotes
        $functionDefaults = [
            'CURRENT_TIMESTAMP',
            'current_timestamp()',
            'now()',
            'CURRENT_TIMESTAMP()',
            'NULL',
            'CURRENT_DATE',
            'CURRENT_TIME',
            'LOCALTIME',
            'LOCALTIMESTAMP'
        ];

        if (in_array(strtoupper((string) $defaultValue), array_map('strtoupper', $functionDefaults))) {
            $definition .= " DEFAULT $defaultValue";
        } else {
            // Escape and quote string defaults
            $escapedDefault = str_replace("'", "''", $defaultValue);
            $definition .= " DEFAULT '$escapedDefault'";
        }
    }

    // Handle EXTRA attributes (AUTO_INCREMENT, ON UPDATE, etc.)
    if (!empty($column['EXTRA'])) {
        $definition .= " {$column['EXTRA']}";
    }

    // Handle COMMENT
    if (!empty($column['COLUMN_COMMENT'])) {
        $escapedComment = str_replace("'", "''", $column['COLUMN_COMMENT']);
        $definition .= " COMMENT '$escapedComment'";
    }

    return $definition;
}

/**
 * Verify that column conversion was successful
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param string $columnName
 * @param string $targetCollation
 * @return bool
 */
function verifyColumnConversion(DatabaseService $db, string $connectionName, string $tableName, string $columnName, string $targetCollation): bool
{
    $schema = $connectionName === 'default' ? $GLOBALS['dbName'] : ($GLOBALS['interfaceDbConfig']['db'] ?? $GLOBALS['dbName']);

    $query = "SELECT COLLATION_NAME
              FROM information_schema.columns
              WHERE table_schema = '$schema'
              AND table_name = '$tableName'
              AND column_name = '$columnName'";

    $result = $db->connection($connectionName)->rawQuery($query);

    return !empty($result) && $result[0]['COLLATION_NAME'] === $targetCollation;
}

/**
 * Converts a table and only the necessary columns to utf8mb4 character set.
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @param bool $dryRun
 * @param bool $skipColumnConversion
 * @return array Results [success, error, skipped counts, etc.]
 */
function convertTableAndColumns(DatabaseService $db, string $connectionName, string $tableName, bool $dryRun = false, bool $skipColumnConversion = false, ?ProgressBar $bar = null): array
{
    global $tableErrors, $columnErrors, $successfulTables, $skippedTables, $verbose;

    $result = [
        'tableName' => $tableName,
        'tableConverted' => false,
        'tableSkipped' => false,
        'tableError' => null,
        'columnsConverted' => 0,
        'columnsSkipped' => 0,
        'columnsWithErrors' => 0,
        'columnErrors' => []
    ];

    $collation = $db->isMySQL8OrHigher() ? 'utf8mb4_0900_ai_ci' : 'utf8mb4_unicode_ci';

    // Check if table needs conversion
    [$tableNeedsConversion, $currentCollation] = tableNeedsConversion($db, $connectionName, $tableName, $collation);

    // Get table size information
    try {
        $tableSizeInfo = $db->connection($connectionName)->rawQuery(
            "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size'
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = '$tableName'"
        );
        $tableSize = empty($tableSizeInfo) ? 'unknown' : $tableSizeInfo[0]['Size'];
    } catch (Throwable $e) {
        $tableSize = 'unknown';
    }

    if (!$tableNeedsConversion) {
        echoMessage("✅ Table $tableName ($tableSize MB) already uses $collation - skipping table conversion", 'green');
        $result['tableSkipped'] = true;
        $skippedTables[] = "$tableName (already using $collation)";
    } else {
        echoMessage("⚙ Converting table: $tableName ($tableSize MB) from $currentCollation to $collation", 'cyan');

        if (!$dryRun) {
            try {
                $startTime = microtime(true);
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE $collation");
                $duration = round(microtime(true) - $startTime, 2);
                echoMessage("✅ Table converted successfully in $duration seconds", 'green');
                $result['tableConverted'] = true;
                $successfulTables[] = $tableName;
            } catch (Throwable $e) {
                $errorMsg = "Failed to convert table '$tableName': " . $e->getMessage();
                echoMessage("❌ $errorMsg", 'red', true); // Always show errors
                LoggerUtility::logError("Failed to convert table $tableName", [
                    'table' => $tableName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);
                $result['tableError'] = $errorMsg;
                $tableErrors[$tableName] = $errorMsg;
                return $result; // Skip column conversion if table conversion failed
            }
        } else {
            echoMessage("🔍 DRY RUN: Would convert table structure to utf8mb4 with $collation", 'yellow');
        }
    }

    if ($skipColumnConversion) {
        echoMessage("⏩ Skipping individual column conversion as requested", 'yellow');
        return $result;
    }

    // Only get columns that need conversion
    $columnsNeedingConversion = getColumnsNeedingConversion($db, $connectionName, $tableName, $collation);

    if ($columnsNeedingConversion === []) {
        echoMessage("✅ All columns in $tableName already use correct collation", 'green');
        return $result;
    }

    echoMessage("⚙ Found " . count($columnsNeedingConversion) . " columns needing conversion in $tableName", 'cyan');

    if (!$dryRun) {
        $totalColumns = count($columnsNeedingConversion);

        foreach ($columnsNeedingConversion as $index => $column) {
            $currentColumn = $index + 1;
            $columnName = $column['COLUMN_NAME'];

            try {
                // Show indexes that might be affected in verbose mode
                if ($verbose) {
                    $indexes = getColumnIndexes($db, $connectionName, $tableName, $columnName);
                    if ($indexes !== []) {
                        echoMessage("    ⚠ Column $columnName has " . count($indexes) . " indexes that may be affected", 'yellow');
                        foreach ($indexes as $index) {
                            $indexType = $index['NON_UNIQUE'] == '0' ? 'UNIQUE' : 'INDEX';
                            echoMessage("      - {$index['INDEX_NAME']} ($indexType, {$index['INDEX_TYPE']})", 'yellow');
                        }
                    }
                }

                if ($bar instanceof \Symfony\Component\Console\Helper\ProgressBar) {
                    MiscUtility::spinnerUpdate($bar, $tableName, $columnName, $currentColumn, $totalColumns);
                }
                echoMessage("  ⚙ Converting column: $columnName (current: {$column['COLLATION_NAME']})", 'cyan', false, $bar);


                // Build complete column definition preserving all properties
                $columnDefinition = buildColumnDefinition($column, $collation);

                if ($verbose) {
                    echoMessage("    SQL: ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition", 'blue');
                }

                $startTime = microtime(true);
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition");

                // Verify the conversion was successful
                if (verifyColumnConversion($db, $connectionName, $tableName, $columnName, $collation)) {
                    $duration = round(microtime(true) - $startTime, 2);
                    echoMessage("  ✅ Column $columnName converted and verified in $duration seconds", 'green');
                    $result['columnsConverted']++;
                } else {
                    $errorMsg = "Column $tableName.$columnName conversion appeared to succeed but verification failed";
                    echoMessage("  ❌ $errorMsg", 'red', true);
                    $result['columnsWithErrors']++;
                    $result['columnErrors'][] = $errorMsg;
                    $columnErrors[] = "$tableName.$columnName: Verification failed";
                }
            } catch (Throwable $e) {
                $errorMsg = "Failed to convert column '$columnName' in table '$tableName': " . $e->getMessage();
                echoMessage("  ❌ $errorMsg", 'red', true); // Always show errors

                // Log the complete column definition that failed
                $failedDefinition = buildColumnDefinition($column, $collation);
                echoMessage("    Failed SQL: ALTER TABLE `$tableName` MODIFY COLUMN $failedDefinition", 'red', true);

                LoggerUtility::logError("Failed to convert column $columnName in table $tableName", [
                    'column' => $columnName,
                    'table' => $tableName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                    'sql' => "ALTER TABLE `$tableName` MODIFY COLUMN $failedDefinition"
                ]);

                $result['columnsWithErrors']++;
                $result['columnErrors'][] = $errorMsg;
                $columnErrors[] = "$tableName.$columnName: " . $e->getMessage();
            }
        }
    } else {
        foreach ($columnsNeedingConversion as $column) {
            $columnDefinition = buildColumnDefinition($column, $collation);
            echoMessage("  🔍 DRY RUN: Would execute: ALTER TABLE `$tableName` MODIFY COLUMN $columnDefinition", 'yellow');
            $result['columnsSkipped']++;
        }
    }

    return $result;
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
function processBatches(array $tables, int $batchSize, callable $processFunction, bool $verbose = true, ?ProgressBar $bar = null): array
{
    $totalTables = count($tables);
    $batches = ceil($totalTables / $batchSize);
    $results = [];


    echoMessage("Processing $totalTables tables in $batches batches of up to $batchSize tables each", 'bold', false, $bar);


    for ($i = 0; $i < $totalTables; $i += $batchSize) {
        $batchTables = array_slice($tables, $i, $batchSize);

        foreach ($batchTables as $index => $tableData) {
            $currentPosition = $i + $index + 1;

            MiscUtility::spinnerUpdate($bar, $tableData['table']);
            if ($verbose) {
                echoMessage("Processing table $currentPosition of $totalTables: {$tableData['table']}", 'bold', false, $bar);
            }

            $results[] = $processFunction($tableData, $currentPosition, $totalTables);
        }

        // Force garbage collection between batches
        if ($batches > 1) {
            echoMessage("Cleaning up memory between batches...", null, $verbose, $bar);
            gc_collect_cycles();
        }
    }

    return $results;
}

/**
 * Display summary of conversion results
 *
 * @param array $results
 */
function displaySummary(array $results): void
{
    global $tableErrors, $columnErrors, $colors;

    $totalColumnsConverted = 0;

    foreach ($results as $result) {
        if (isset($result['columnsConverted'])) {
            $totalColumnsConverted += $result['columnsConverted'];
        }
    }

    // Display errors if any
    if (!empty($tableErrors)) {
        echo PHP_EOL . $colors['bold'] . $colors['red'] . "TABLE ERRORS:" . $colors['reset'] . PHP_EOL;
        foreach ($tableErrors as $table => $error) {
            echo "- $table: $error" . PHP_EOL;
        }
    }

    if (!empty($columnErrors)) {
        echo PHP_EOL . $colors['bold'] . $colors['red'] . "COLUMN ERRORS:" . $colors['reset'] . PHP_EOL;
        foreach ($columnErrors as $error) {
            echo "- $error" . PHP_EOL;
        }
    }

    // Final status message
    if (empty($tableErrors) && empty($columnErrors)) {
        echo PHP_EOL . $colors['bold'] . $colors['green'] . "✅ Collation check & conversion completed successfully!" . $colors['reset'] . PHP_EOL;
    } else {
        echo PHP_EOL . $colors['bold'] . $colors['yellow'] . "⚠ Conversion completed with some errors." . $colors['reset'] . PHP_EOL;
    }
}

try {
    // If specific table provided, only process that one
    if ($specificTable) {
        try {
            $tablesList = fetchTables($db, $dbName, 'default', $specificTable);
            $allTables = array_map(fn($table): array => ['table' => $table, 'connection' => 'default'], $tablesList);
        } catch (Exception $e) {
            // If not found in default DB, try interface DB
            if ($interfaceDbConfig) {
                $interfaceDbName = $interfaceDbConfig['db'] ?? null;
                if ($interfaceDbName) {
                    $tablesList = fetchTables($db, $interfaceDbName, 'interface', $specificTable);
                    $allTables = array_map(fn($table): array => ['table' => $table, 'connection' => 'interface'], $tablesList);
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

        if ($tablesList === [] && $interfaceTablesList === []) {
            throw new Exception("No tables found for conversion.");
        }

        // Merge tables and include connection info
        $allTables = array_merge(
            array_map(fn($table): array => ['table' => $table, 'connection' => 'default'], $tablesList),
            array_map(fn($table): array => ['table' => $table, 'connection' => 'interface'], $interfaceTablesList)
        );
    }

    $totalTables = count($allTables);
    echo PHP_EOL;
    echoMessage("Starting collation conversion process for $totalTables tables...", 'bold', true);

    // Start timer
    $scriptStartTime = microtime(true);
    $bar = MiscUtility::spinnerStart($totalTables, 'Preparing…');

    // Process tables in batches and collect results
    $results = processBatches(
        $allTables,
        $batchSize,
        function (array $tableData, $current, $total) use ($db, $dryRun, $skipColumnConversion, $verbose,  $bar): array {
            $table = $tableData['table'];

            // show the table on the spinner
            MiscUtility::spinnerUpdate($bar, $table);

            if ($verbose) {
                // optional verbose line without breaking the bar
                echoMessage("Processing table $current of $total: {$table}", 'bold', false, $bar);
            }

            $res = convertTableAndColumns($db, $tableData['connection'], $table, $dryRun, $skipColumnConversion, $bar);

            // one tick per table
            MiscUtility::spinnerAdvance($bar);
            return $res;
        },
        $verbose,
        $bar
    );

    MiscUtility::spinnerUpdate($bar, '', null, $totalTables, $totalTables);
    MiscUtility::spinnerFinish($bar);

    $totalDuration = microtime(true) - $scriptStartTime;

    // Display summary
    displaySummary($results);
} catch (Throwable $e) {
    echoMessage("An error occurred during the conversion process:" . $e->getFile() . ":" . $e->getLine() . " = " . $e->getMessage(), 'red', true);
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
