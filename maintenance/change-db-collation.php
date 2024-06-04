#!/usr/bin/env php
<?php

/**
 * This script converts the database tables and columns to use the utf8mb4 character set.
 * This is necessary to support emojis and other special characters.
 *
 * Note: This script should be run from the command line.
 */

if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once(__DIR__ . '/../bootstrap.php');

use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$dbName = SYSTEM_CONFIG['database']['db'];
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

function convertTableAndColumns(DatabaseService $db, string $tableName)
{
    echo PHP_EOL . "Converting table: $tableName" . PHP_EOL;
    $db->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    // Convert individual columns if necessary
    $columns = $db->rawQuery("SHOW FULL COLUMNS FROM `$tableName`");
    foreach ($columns as $column) {
        if (preg_match('/char|varchar|text|tinytext|mediumtext|longtext/i', $column['Type'])) {
            if ($column['Collation'] !== 'utf8mb4_general_ci') {
                $db->rawQuery("ALTER TABLE `$tableName` MODIFY `{$column['Field']}` {$column['Type']} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            }
        }
    }
}

try {
    // Fetch the list of tables
    $tables = $db->rawQuery("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = ?", [$dbName]);

    if (!$tables) {
        throw new Exception("No tables found in the database $dbName.");
    }

    $tablesList = array_map(function ($table) {
        return $table['TABLE_NAME'] ?? null;
    }, $tables);

    if (empty($tablesList)) {
        throw new Exception("Table list is empty for database $dbName.");
    }

    // Convert each table and its columns with a progress bar
    $totalTables = count($tablesList);
    foreach ($tablesList as $index => $table) {
        if (!empty($table)) {
            MiscUtility::displayProgressBar($index + 1, $totalTables);
            convertTableAndColumns($db, $table);
        }
    }

    echo "Conversion process completed for database $dbName" . PHP_EOL;
} catch (Exception $e) {
    error_log("Conversion failed: " . $e->getMessage());
    echo "Conversion process failed for database $dbName. Error: " . $e->getMessage() . "\n";
}
