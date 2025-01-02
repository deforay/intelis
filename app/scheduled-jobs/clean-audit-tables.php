<?php

require_once(__DIR__ . "/../../bootstrap.php");

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$cliMode = php_sapi_name() === 'cli';
$lockFile = MiscUtility::getLockFile(__FILE__);

if (MiscUtility::fileExists($lockFile) && !MiscUtility::isLockFileExpired($lockFile, maxAgeInSeconds: 18000)) {
    if ($cliMode) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

$tableToTestTypeMap = [
    'audit_form_vl' => 'vl',
    'audit_form_eid' => 'eid',
    'audit_form_covid19' => 'covid19',
    'audit_form_tb' => 'tb',
    'audit_form_hepatitis' => 'hepatitis',
    'audit_form_generic' => 'generic',
];

try {
    foreach ($tableToTestTypeMap as $tableName => $testType) {
        if ($cliMode) {
            echo "Processing duplicates for table: $tableName ($testType)" . PHP_EOL;
        }

        $primaryKeyColumn = TestsService::getTestPrimaryKeyColumn($testType);

        // Define ignored columns
        $ignoredColumns = ['dt_datetime', 'action', 'revision', 'last_modified_datetime'];

        // Get the table fields excluding the ignored columns
        $tableFields = $db->getTableFieldsAsArray($tableName, $ignoredColumns);
        $groupByColumns = implode(', ', array_keys($tableFields));

        // Step 1: Check for duplicates
        $checkDuplicatesQuery = "
            SELECT
                $primaryKeyColumn,
                MIN(dt_datetime) AS oldest_datetime,
                COUNT(*) AS duplicate_count
            FROM $tableName
            GROUP BY $primaryKeyColumn, $groupByColumns
            HAVING COUNT(*) > 1
        ";

        $duplicates = $db->rawQuery($checkDuplicatesQuery);

        if (empty($duplicates)) {
            if ($cliMode) {
                echo "No duplicates found in $tableName." . PHP_EOL;
            }
            continue;
        }

        if ($cliMode) {
            echo "Processing $tableName ($testType): " . count($duplicates) . " duplicate groups found." . PHP_EOL;
        }


        // Step 2: Delete duplicates using $duplicates data
        foreach ($duplicates as $group) {
            $vlSampleId = $group[$primaryKeyColumn];
            $oldestDatetime = $group['oldest_datetime'];

            $deleteQuery = "
                DELETE FROM $tableName
                WHERE $primaryKeyColumn = ?
                AND dt_datetime != ?
            ";

            $db->rawQuery($deleteQuery, [$vlSampleId, $oldestDatetime]);
        }

        if ($cliMode) {
            echo "Duplicates removed successfully for $tableName." . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    if ($cliMode) {
        echo "Error occurred: " . $e->getMessage() . PHP_EOL;
    }
    LoggerUtility::logError($e->getMessage(), [
        'table' => $tableName,
        'test_type' => $testType,
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

} finally {
    MiscUtility::deleteLockFile(__FILE__);
}
