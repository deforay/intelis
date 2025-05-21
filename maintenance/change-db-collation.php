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
$options = getopt('dbtsv', ['dry-run', 'batch-size:', 'table:', 'skip-columns', 'verbose']);
$dryRun = isset($options['dry-run']) || isset($options['d']);
$batchSize = isset($options['batch-size']) ? (int)$options['batch-size'] : (isset($options['b']) ? (int)$options['b'] : 10);
$specificTable = isset($options['table']) ? $options['table'] : (isset($options['t']) ? $options['t'] : null);
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
function echoMessage(string $message, ?string $color = null, bool $alwaysShow = false) {
    global $verbose, $colors;

    if (!$verbose && !$alwaysShow) return;

    if ($color && isset($colors[$color])) {
        echo $colors[$color] . $message . $colors['reset'] . PHP_EOL;
    } else {
        echo $message . PHP_EOL;
    }
}

/**
 * Custom progress bar that shows the current table name
 *
 * @param int $current Current position
 * @param int $total Total items
 * @param string $tableName Current table name
 * @param int $size Progress bar size
 */
function customProgressBar(int $current, int $total, string $tableName, int $size = 30): void
{
    static $startTime;

    // Initialize the timer on the first call
    if (!isset($startTime)) {
        $startTime = time();
    }

    // Calculate elapsed time
    $elapsed = time() - $startTime;

    // Calculate progress percentage
    $progress = ($current / $total);
    $barLength = (int) floor($progress * $size);

    // Generate the progress bar
    $progressBar = str_repeat('=', $barLength) . str_repeat(' ', $size - $barLength);

    // Truncate table name if too long
    $displayName = (strlen($tableName) > 20) ? substr($tableName, 0, 17) . '...' : $tableName;

    // Output the progress bar with current table name
    printf("\r[%s] %3d%% (%d/%d) - %s - %d sec elapsed",
        $progressBar,
        $progress * 100,
        $current,
        $total,
        $displayName,
        $elapsed
    );

    // Flush output for real-time updates
    fflush(STDOUT);

    // Print a newline and reset the timer when done
    if ($current === $total) {
        echo PHP_EOL;
        $startTime = null; // Reset timer for reuse
    }
}

// Show basic info - always display regardless of verbose setting
echoMessage("Mode: " . ($dryRun ? "Dry Run (no changes will be made)" : "Live Run"), 'bold', true);
echoMessage("Batch Size: $batchSize tables at a time", 'bold', true);
echoMessage("Verbose Mode: " . ($verbose ? "ON" : "OFF"), 'bold', true);

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
 * @return array Results [success, error, skipped counts, etc.]
 */
function convertTableAndColumns(DatabaseService $db, string $connectionName, string $tableName, bool $dryRun = false, bool $skipColumnConversion = false): array
{
    global $tableErrors, $columnErrors, $successfulTables, $skippedTables;

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
    list($tableNeedsConversion, $currentCollation) = tableNeedsConversion($db, $connectionName, $tableName, $collation);

    // Get table size information
    try {
        $tableSizeInfo = $db->connection($connectionName)->rawQuery(
            "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Size'
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name = '$tableName'"
        );
        $tableSize = !empty($tableSizeInfo) ? $tableSizeInfo[0]['Size'] : 'unknown';
    } catch (Throwable $e) {
        $tableSize = 'unknown';
    }

    if (!$tableNeedsConversion) {
        echoMessage("✓ Table $tableName ($tableSize MB) already uses $collation - skipping table conversion", 'green');
        $result['tableSkipped'] = true;
        $skippedTables[] = "$tableName (already using $collation)";
    } else {
        echoMessage("⚙ Converting table: $tableName ($tableSize MB) from $currentCollation to $collation", 'cyan');

        if (!$dryRun) {
            try {
                $startTime = microtime(true);
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE $collation");
                $duration = round(microtime(true) - $startTime, 2);
                echoMessage("✓ Table converted successfully in $duration seconds", 'green');
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

    if (empty($columnsNeedingConversion)) {
        echoMessage("✓ All columns in $tableName already use correct collation", 'green');
        return $result;
    }

    echoMessage("⚙ Found " . count($columnsNeedingConversion) . " columns needing conversion in $tableName", 'cyan');

    if (!$dryRun) {
        $totalColumns = count($columnsNeedingConversion);
        foreach ($columnsNeedingConversion as $index => $column) {
            try {
                $currentColumn = $index + 1;
                // Use for column conversion within a table
                if ($totalColumns > 1) {
                    $columnName = $column['Field'];
                    printf("\r  Column %d/%d: %s", $currentColumn, $totalColumns, $columnName);
                    fflush(STDOUT);
                }

                $null = $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL';
                $default = $column['Default'] !== null ? "DEFAULT '" . $db->connection($connectionName)->escape($column['Default']) . "'" : '';
                $extra = $column['Extra'] ?? '';

                echoMessage("  ⚙ Converting column: {$column['Field']} (current collation: {$column['Collation']})", 'cyan');
                $startTime = microtime(true);

                $columnDefinition = "`{$column['Field']}` {$column['Type']} CHARACTER SET utf8mb4 COLLATE $collation $null $default $extra";
                $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` MODIFY $columnDefinition");

                $duration = round(microtime(true) - $startTime, 2);
                echoMessage("  ✓ Column {$column['Field']} converted in $duration seconds", 'green');
                $result['columnsConverted']++;
            } catch (Throwable $e) {
                $errorMsg = "Failed to convert column '{$column['Field']}' in table '$tableName': " . $e->getMessage();
                echoMessage("  ❌ $errorMsg", 'red', true); // Always show errors
                LoggerUtility::logError("Failed to convert column {$column['Field']} in table $tableName", [
                    'column' => $column['Field'],
                    'table' => $tableName,
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);
                $result['columnsWithErrors']++;
                $result['columnErrors'][] = $errorMsg;
                $columnErrors[] = "$tableName.{$column['Field']}: " . $e->getMessage();
            }
        }

        // Add a newline after column progress completes
        if ($totalColumns > 1) {
            echo PHP_EOL;
        }
    } else {
        foreach ($columnsNeedingConversion as $column) {
            echoMessage("  🔍 DRY RUN: Would convert column '{$column['Field']}' from {$column['Collation']} to $collation", 'yellow');
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
function processBatches(array $tables, int $batchSize, callable $processFunction, $verbose = true): array
{
    $totalTables = count($tables);
    $batches = ceil($totalTables / $batchSize);
    $results = [];

    echoMessage("Processing $totalTables tables in $batches batches of up to $batchSize tables each", 'bold', true);

    for ($i = 0; $i < $totalTables; $i += $batchSize) {
        $batchTables = array_slice($tables, $i, $batchSize);
        $batchNumber = floor($i / $batchSize) + 1;

        echoMessage("Starting batch $batchNumber of $batches...", 'bold', true);
        $startTime = microtime(true);

        foreach ($batchTables as $index => $tableData) {
            $currentPosition = $i + $index + 1;
            // Show overall progress using custom progress bar with table name
            customProgressBar($currentPosition, $totalTables, $tableData['table']);

            if ($verbose) {
                echo PHP_EOL; // Add a line break for verbose output
                echoMessage("Processing table $currentPosition of $totalTables: {$tableData['table']}", 'bold', true);
            }

            $results[] = $processFunction($tableData, $currentPosition, $totalTables);
        }

        $duration = round(microtime(true) - $startTime, 2);
        echo PHP_EOL; // Ensure a line break after the progress bar
        echoMessage("Completed batch $batchNumber in $duration seconds", 'bold', true);

        // Force garbage collection between batches
        if ($batches > 1) {
            echoMessage("Cleaning up memory between batches...", null);
            gc_collect_cycles();
        }
    }

    return $results;
}

/**
 * Display summary of conversion results
 *
 * @param array $results
 * @param float $totalDuration
 */
function displaySummary(array $results, float $totalDuration): void
{
    global $tableErrors, $columnErrors, $successfulTables, $skippedTables, $colors;

    // Count various outcomes
    $tablesConverted = count($successfulTables);
    $tablesSkipped = count($skippedTables);
    $tablesWithErrors = count($tableErrors);

    $totalColumnsConverted = 0;
    $totalColumnsWithErrors = count($columnErrors);

    foreach ($results as $result) {
        if (isset($result['columnsConverted'])) {
            $totalColumnsConverted += $result['columnsConverted'];
        }
    }

    // Display summary header
    echo PHP_EOL . $colors['bold'] . "=======================================" . $colors['reset'] . PHP_EOL;
    echo $colors['bold'] . "         CONVERSION SUMMARY         " . $colors['reset'] . PHP_EOL;
    echo $colors['bold'] . "=======================================" . $colors['reset'] . PHP_EOL;

    // Overall statistics
    echo $colors['bold'] . "Total Duration: " . $colors['reset'] . round($totalDuration, 2) . " seconds" . PHP_EOL;
    echo $colors['bold'] . "Tables Processed: " . $colors['reset'] . count($results) . PHP_EOL;

    // Table statistics with color coding
    echo $colors['bold'] . "Tables Converted: " . $colors['reset'] .
         $colors['green'] . $tablesConverted . $colors['reset'] . PHP_EOL;

    echo $colors['bold'] . "Tables Skipped: " . $colors['reset'] .
         $colors['yellow'] . $tablesSkipped . $colors['reset'] . PHP_EOL;

    echo $colors['bold'] . "Tables With Errors: " . $colors['reset'] .
         ($tablesWithErrors > 0 ? $colors['red'] . $tablesWithErrors . $colors['reset'] : "0") . PHP_EOL;

    // Column statistics
    echo $colors['bold'] . "Columns Converted: " . $colors['reset'] .
         $colors['green'] . $totalColumnsConverted . $colors['reset'] . PHP_EOL;

    echo $colors['bold'] . "Columns With Errors: " . $colors['reset'] .
         ($totalColumnsWithErrors > 0 ? $colors['red'] . $totalColumnsWithErrors . $colors['reset'] : "0") . PHP_EOL;

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
        echo PHP_EOL . $colors['bold'] . $colors['green'] . "✓ All operations completed successfully!" . $colors['reset'] . PHP_EOL;
    } else {
        echo PHP_EOL . $colors['bold'] . $colors['yellow'] . "⚠ Conversion completed with some errors." . $colors['reset'] . PHP_EOL;
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
    echoMessage("Starting conversion process for $totalTables tables...", 'bold', true);

    // Start timer
    $scriptStartTime = microtime(true);

    // Process tables in batches and collect results
    $results = processBatches($allTables, $batchSize, function($tableData, $current, $total) use ($db, $dryRun, $skipColumnConversion) {
        return convertTableAndColumns($db, $tableData['connection'], $tableData['table'], $dryRun, $skipColumnConversion);
    });

    $totalDuration = microtime(true) - $scriptStartTime;

    // Display summary
    displaySummary($results, $totalDuration);

} catch (Throwable $e) {
    echoMessage("An error occurred during the conversion process:" . $e->getFile() . ":" . $e->getLine() . " = " . $e->getMessage(), 'red', true);
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
