<?php

use App\Registries\ContainerRegistry;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

$auditTables = [
    'form_vl' => 'audit_form_vl',
    'form_eid' => 'audit_form_eid',
    'form_covid19' => 'audit_form_covid19',
    'form_tb' => 'audit_form_tb',
    'form_hepatitis' => 'audit_form_hepatitis',
    'form_generic' => 'audit_form_generic',
];

foreach ($auditTables as $formTable => $auditTable) {
    // Check if the audit table exists
    $auditTableExists = $db->rawQuery("SHOW TABLES LIKE ?", [$auditTable]);

    if (!$auditTableExists) {
        echo "Table $auditTable does not exist. Skipping...<br>";
        continue;
    }

    // Query to find the missing columns
    $query = "
    SELECT COLUMN_NAME, COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'vlsm'
    AND TABLE_NAME = '$formTable'
    AND COLUMN_NAME NOT IN (
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'vlsm'
        AND TABLE_NAME = '$auditTable'
    )
    ORDER BY COLUMN_NAME;
    ";

    $columns = $db->rawQuery($query);

    if (!$columns) {
        echo "No missing columns found between $formTable and $auditTable or an error occurred.<br>";
        continue;
    }

    // Loop through the result and generate ALTER TABLE statements
    foreach ($columns as $column) {
        $alterQuery = "ALTER TABLE $auditTable ADD " . $column['COLUMN_NAME'] . " " . $column['COLUMN_TYPE'] . ";";

        if (!$db->rawQuery($alterQuery)) {
            echo "Error altering $auditTable for column " . $column['COLUMN_NAME'] . ": " . $db->getLastError() . "<br>";
        } else {
            echo "Column " . $column['COLUMN_NAME'] . " added to $auditTable successfully.<br>";
        }
    }
}
