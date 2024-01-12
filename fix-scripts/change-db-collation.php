#!/usr/bin/env php
<?php

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use mysqli_sql_exception;

require_once(__DIR__ . '/../bootstrap.php');

// Assuming SYSTEM_CONFIG is defined and accessible here
$dbName = SYSTEM_CONFIG['database']['db'];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Function to convert table and column collation and character set
function convertTableAndColumns($db, $tableName)
{
    try {
        // Change table default character set and collation
        $db->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        // Get columns for the table
        $columns = $db->rawQuery("SHOW FULL COLUMNS FROM `$tableName`");

        // Change each column's character set and collation
        foreach ($columns as $column) {
            $field = $column['Field'];
            $type = $column['Type'];
            if (str_contains($type, 'char') || str_contains($type, 'text')) {
                $db->rawQuery("ALTER TABLE `$tableName` MODIFY `$field` $type CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Error processing table $tableName: " . $e->getMessage());
    }
}

// Get list of tables
try {
    $tables = $db->rawQuery("SHOW TABLES FROM `$dbName`");
    $tablesList = array_column($tables, "Tables_in_$dbName");

    // Iterate through each table and convert its character set and collation
    foreach ($tablesList as $table) {
        convertTableAndColumns($db, $table);
    }

    echo "Conversion to utf8mb4 and utf8mb4_general_ci completed for database $dbName.\n";
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching tables from database $dbName: " . $e->getMessage());
}
