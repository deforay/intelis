#!/usr/bin/env php
<?php

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once __DIR__ . '/../bootstrap.php';

function createAndModifyAuditTable($db, $fromDbName, $toDbName, $formTable, $auditTable)
{
    echo "Table $toDbName.$auditTable does not exist. Creating...\n";
    $createTableQuery = "CREATE TABLE $toDbName.$auditTable SELECT * FROM $fromDbName.$formTable WHERE 1=0;";
    $modifyTableQuery = "ALTER TABLE `$toDbName`.`$auditTable`
                        MODIFY COLUMN `sample_id` int(11) NOT NULL,
                        ENGINE = MyISAM,
                        ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
                        ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
                        ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
                        ADD PRIMARY KEY (`sample_id`, `revision`)";

    // Execute the create and modify table queries
    $db->rawQuery($createTableQuery);
    $db->rawQuery($modifyTableQuery);
}

function setupTriggers($mysqli, $fromDbName, $toDbName, $formTable, $auditTable)
{
    // Set up the triggers
    $triggers = [
        "{$formTable}_data__ai" => "AFTER INSERT",
        "{$formTable}_data__au" => "AFTER UPDATE",
        "{$formTable}_data__bd" => "BEFORE DELETE",
    ];

    foreach ($triggers as $triggerName => $timing) {
        $action = strtolower(str_replace($formTable . '_data__', '', $triggerName));
        $triggerQuery = "DROP TRIGGER IF EXISTS `$triggerName`;
                            CREATE TRIGGER `$triggerName` $timing ON `$fromDbName`.`$formTable`
                            FOR EACH ROW
                            INSERT INTO `$toDbName`.`$auditTable` SELECT '$action', NULL, NOW(), d.*
                            FROM `$fromDbName`.`$formTable` AS d
                            WHERE d.sample_id = " . ($action === 'delete' ? "OLD" : "NEW") . ".sample_id";

        // Execute the DROP TRIGGER and CREATE TRIGGER statements directly
        if (!$mysqli->multi_query($triggerQuery)) {
            echo "Error executing trigger statement for $triggerName: {$mysqli->error}\n";
            do {
                if ($res = $mysqli->store_result()) {
                    $res->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());
        } else {
            echo "Trigger $triggerName created successfully.\n";
        }
    }
}

function addMissingColumns($db, $fromDbName, $toDbName, $formTable, $auditTable)
{
    // Query to find the missing columns
    $query = "SELECT COLUMN_NAME, COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = '$fromDbName'
                AND TABLE_NAME = '{$db->escape($formTable)}'
                AND COLUMN_NAME NOT IN
                    (SELECT COLUMN_NAME
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = '$toDbName'
                        AND TABLE_NAME = '{$db->escape($auditTable)}')
                ORDER BY COLUMN_NAME";

    $columns = $db->rawQuery($query);

    if ($db->getLastErrno() > 0) {
        echo "Error checking columns for $fromDbName.$formTable and $toDbName.$auditTable: " . $db->getLastError() . "\n";
        return;
    }

    if (empty($columns)) {
        echo "All columns for $fromDbName.$formTable exist in $toDbName.$auditTable.\n";
        return;
    }

    // Loop through the result and generate ALTER TABLE statements
    foreach ($columns as $column) {
        $alterQuery = "ALTER TABLE `$toDbName`.`$auditTable` ADD `" . $column['COLUMN_NAME'] . "` " . $column['COLUMN_TYPE'] . ";";

        if (!$db->rawQuery($alterQuery)) {
            echo "Error altering $toDbName.$auditTable for column " . $column['COLUMN_NAME'] . ": " . $db->getLastError() . "\n";
        } else {
            echo "Column " . $column['COLUMN_NAME'] . " added to $toDbName.$auditTable successfully.\n";
        }
    }
}

function dropExtraColumns($db, $fromDbName, $toDbName, $formTable, $auditTable)
{
    $allowedExtraColumns = [
        'action',
        'revision',
        'dt_datetime',
    ];
    $query = "SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = '$toDbName'
                AND TABLE_NAME = '{$db->escape($auditTable)}'
                AND COLUMN_NAME NOT IN (
                    SELECT COLUMN_NAME
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = '$fromDbName'
                    AND TABLE_NAME = '{$db->escape($formTable)}')
                    AND COLUMN_NAME NOT IN ('" . implode("','", $allowedExtraColumns) . "')
                ORDER BY COLUMN_NAME";

    $extraColumns = $db->rawQuery($query);

    if ($db->getLastErrno() > 0) {
        echo "Error checking extra columns for $toDbName.$auditTable and $fromDbName.$formTable: " . $db->getLastError() . "\n";
    } else {
        foreach ($extraColumns as $column) {
            // if (in_array($column['COLUMN_NAME'], $allowedExtraColumns)) {
            //     continue;
            // }
            $dropColumnQuery = "ALTER TABLE `$toDbName`.`$auditTable` DROP COLUMN `" . $column['COLUMN_NAME'] . "`;";
            if (!$db->rawQuery($dropColumnQuery)) {
                echo "Error dropping column " . $column['COLUMN_NAME'] . " from $toDbName.$auditTable: " . $db->getLastError() . "\n";
            } else {
                echo "Column " . $column['COLUMN_NAME'] . " dropped from $toDbName.$auditTable successfully.\n";
            }
        }
    }
}


function processAuditTables($db, $fromDbName, $toDbName, $setupTriggers = true)
{
    $auditTables = [
        'form_vl' => 'audit_form_vl',
        'form_eid' => 'audit_form_eid',
        'form_covid19' => 'audit_form_covid19',
        'form_tb' => 'audit_form_tb',
        'form_hepatitis' => 'audit_form_hepatitis',
        'form_generic' => 'audit_form_generic',
    ];

    // Get the mysqli connection from the DatabaseService instance
    $mysqli = $db->mysqli();
    foreach ($auditTables as $formTable => $auditTable) {

        $mysqli->begin_transaction();

        // Check if the audit table exists
        $auditTableEscaped = $db->escape($auditTable);
        try {
            $auditTableExists = $db->rawQuery("SHOW TABLES IN `$toDbName` LIKE '$auditTableEscaped'");
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Error checking existence of $toDbName.$auditTable: " . $e->getMessage());
            continue;  // Skip to the next table if we can't even check existence
        }
        try {

            if (!$auditTableExists) {

                createAndModifyAuditTable($db, $fromDbName, $toDbName, $formTable, $auditTable);

                if ($setupTriggers) {
                    setupTriggers($mysqli, $fromDbName, $toDbName, $formTable, $auditTable);
                }

                echo "Audit table $toDbName.$auditTable and triggers created successfully.\n";
            } else {

                addMissingColumns($db, $fromDbName, $toDbName, $formTable, $auditTable);

                // After adding missing columns, now check for extra columns in audit table
                dropExtraColumns($db, $fromDbName, $toDbName, $formTable, $auditTable);
            }
            $mysqli->commit();
        } catch (Exception $e) {
            $mysqli->rollback();
            LoggerUtility::log('error', $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            continue;
        }
    }
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$mainDbName = SYSTEM_CONFIG['database']['db'];

if ($db->isConnected() === false) {
    exit("Database connection failed. Please check your database settings\n");
}
processAuditTables($db->connection('default'), $mainDbName, $mainDbName);
