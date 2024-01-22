#!/usr/bin/env php
<?php

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');

$dbName = SYSTEM_CONFIG['database']['db'];
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

function needsTableConversion($db, $tableName)
{
    global $dbName; // Access the global variable

    $tableInfo = $db->rawQueryOne("SELECT TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$tableName}'");
    return $tableInfo['TABLE_COLLATION'] !== 'utf8mb4_general_ci';
}

function needsColumnConversion($db, $tableName, $columnName, $columnType)
{
    global $dbName; // Access the global variable

    $columnInfo = $db->rawQueryOne("SELECT CHARACTER_SET_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$tableName}' AND COLUMN_NAME = '{$columnName}'");
    return (str_contains($columnType, 'char') || str_contains($columnType, 'text')) && ($columnInfo['CHARACTER_SET_NAME'] !== 'utf8mb4' || $columnInfo['COLLATION_NAME'] !== 'utf8mb4_general_ci');
}



function convertTableAndColumns($db, $tableName)
{
    /** @var DatabaseService $db */
    $db->startTransaction();

    // Convert table if necessary
    if (needsTableConversion($db, $tableName)) {
        $db->rawQuery("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        //echo "Converted table $tableName to utf8mb4 and utf8mb4_general_ci.\n";
    } else {
        //echo "Table $tableName already has the desired collation and character set.\n";
    }

    // Convert individual columns if necessary
    $columns = $db->rawQuery("SHOW FULL COLUMNS FROM `$tableName`");
    foreach ($columns as $column) {
        if (needsColumnConversion($db, $tableName, $column['Field'], $column['Type'])) {
            $db->rawQuery("ALTER TABLE `$tableName` MODIFY `{$column['Field']}` {$column['Type']} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            //echo "Converted column {$column['Field']} in table $tableName to utf8mb4 and utf8mb4_general_ci.\n";
        }
    }

    $db->commit();
}


try {
    $tables = $db->rawQuery("SHOW TABLES FROM `$dbName`");
    $tablesList = array_column($tables, "Tables_in_$dbName");

    foreach ($tablesList as $table) {
        convertTableAndColumns($db, $table);
    }

    echo "Conversion process completed for database $dbName.\n";
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching tables from database $dbName: " . $e->getMessage());
}
