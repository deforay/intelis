#!/usr/bin/env php
<?php

/**
 * This script converts the database tables and columns to use the utf8mb4 character set.
 * It ensures compatibility with emojis and other special characters.
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

$dbName = SYSTEM_CONFIG['database']['db'];
$interfaceDbConfig = null;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


if (!isset(SYSTEM_CONFIG['interfacing']['enabled']) || SYSTEM_CONFIG['interfacing']['enabled'] !== false) {
    $db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);
    $interfaceDbConfig = SYSTEM_CONFIG['interfacing']['database'] ?? null;
}

/**
 * Converts a table and its text-based columns to utf8mb4 character set.
 *
 * @param DatabaseService $db
 * @param string $connectionName
 * @param string $tableName
 * @throws Exception
 */
function convertTableAndColumns(DatabaseService $db, string $connectionName, string $tableName): void
{
    echo PHP_EOL . "Converting table: $tableName" . PHP_EOL;
    $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Convert individual text-based columns if necessary
    $columns = $db->connection($connectionName)->rawQuery("SHOW FULL COLUMNS FROM `$tableName`");
    foreach ($columns as $column) {
        if (preg_match('/char|varchar|text|tinytext|mediumtext|longtext/i', $column['Type']) && $column['Collation'] !== 'utf8mb4_unicode_ci') {
            $db->connection($connectionName)->rawQuery("ALTER TABLE `$tableName` MODIFY `{$column['Field']}` {$column['Type']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }
}

/**
 * Retrieves a list of tables from a given database.
 *
 * @param DatabaseService $db
 * @param string $schema
 * @param string $connectionName
 * @return array
 * @throws Exception
 */
function fetchTables(DatabaseService $db, string $schema, string $connectionName): array
{
    $tables = $db->connection($connectionName)->rawQuery("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ?", [$schema]);

    if (!$tables) {
        throw new Exception("No tables found in the database $schema (connection: $connectionName).");
    }

    return array_map(fn($table) => $table['TABLE_NAME'] ?? null, $tables);
}

try {
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

    $totalTables = count($allTables);
    echo "Starting conversion process for $totalTables tables..." . PHP_EOL;

    // Convert each table and its columns
    foreach ($allTables as $index => $tableData) {
        MiscUtility::progressBar($index + 1, $totalTables);
        convertTableAndColumns($db, $tableData['connection'], $tableData['table']);
    }

    echo PHP_EOL . "Conversion process completed successfully." . PHP_EOL;
} catch (Throwable $e) {
    echo PHP_EOL . "An error occurred during the conversion process." . PHP_EOL;
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
