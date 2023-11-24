#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

if (
    !isset(SYSTEM_CONFIG['archive']['enabled']) ||
    SYSTEM_CONFIG['archive']['enabled'] === false
) {
    error_log('Archiving is not enabled. Please enable it in configuration.');
    exit;
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

if (empty(SYSTEM_CONFIG['archive'])) {
    echo "No archive database settings in System Config";
    exit(0);
}

if (
    !empty(SYSTEM_CONFIG['archive']['database']['host']) &&
    !empty(SYSTEM_CONFIG['archive']['database']['username'])
) {
    $db->addConnection('archive', SYSTEM_CONFIG['archive']['database']);
}

$auditTables = [
    'audit_form_vl',
    'audit_form_eid',
    'audit_form_covid19',
    'audit_form_tb',
    'audit_form_hepatitis',
    'audit_form_generic',
];



// $idField = [
//     'audit_form_vl' => 'vl_sample_id',
//     'audit_form_eid' => 'eid_id',
//     'audit_form_covid19' => 'covid19_id',
//     'audit_form_tb' => 'tb_id',
//     'audit_form_hepatitis' => 'hepatitis_id',
//     'audit_form_generic' => 'sample_id',
// ];

$archiveBeforeDate = DateUtility::getDateBeforeMonths(6);

$mainDbName = SYSTEM_CONFIG['database']['db'];
$archiveDbName = SYSTEM_CONFIG['archive']['database']['db'];

foreach ($auditTables as $auditTable) {

    echo "Archiving data from $mainDbName.$auditTable to $archiveDbName.$auditTable...\n";
    //$sampleIdField = $idField[$auditTable];

    try {

        echo "Checking and preparing table $auditTable in the archive database...\n";

        // Check if the audit table exists
        if (!$db->connection('archive')->tableExists([$auditTable])) {
            // Create the table in the archive database using the structure of the main database table
            $createQuery = "CREATE TABLE $archiveDbName.$auditTable SELECT * FROM $mainDbName.$auditTable WHERE 1=0";
            $db->connection('archive')->rawQuery($createQuery);
            echo "Created table $auditTable in the archive database.\n";
        }

        $limit = 1000;
        $offset = 0;

        do {
            echo "Processing $limit rows starting from offset $offset\n";
            // Select data to be archived
            $db->connection('default')->where('dt_datetime', $archiveBeforeDate, '<');
            $db->connection('default')->orderBy('dt_datetime', 'asc');
            $dataToArchive = $db->connection('default')->get($auditTable, [$offset, $limit]);

            $rowCount = count($dataToArchive);
            echo "Fetched $rowCount rows\n";

            // Check if there is data to archive
            if ($rowCount > 0) {
                // Insert data into the archive database
                foreach ($dataToArchive as $row) {
                    $db->connection('archive')->insert($auditTable, $row);
                }

                // Delete archived data from the main database
                foreach ($dataToArchive as $row) {
                    $db->connection('default')
                        ->where('dt_datetime', $row['dt_datetime'])
                        //->where('revision', $row['revision'])
                        ->where('unique_id', $row['unique_id'])
                        ->delete($auditTable);
                }

                $offset += $limit;
                echo "Processed $rowCount rows, moving to next batch...\n";
            } else {
                echo "No more data to process in $auditTable.\n";
            }
        } while ($rowCount > 0);

        echo "Archived and deleted data older than 6 months from $auditTable.\n";
    } catch (Exception $e) {
        throw new SystemException($e->getMessage());
    }
}

echo "Archiving process completed.\n";
