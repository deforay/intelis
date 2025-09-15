<?php

// only run from command line
$isCli = php_sapi_name() === 'cli';
if ($isCli === false) {
    exit(0);
}


require_once(__DIR__ . "/../bootstrap.php");

declare(ticks=1);

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$lockFile = MiscUtility::getLockFile(__FILE__);

if (!MiscUtility::isLockFileExpired($lockFile)) {
    if ($isCli) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

$tableToTestTypeMap = [
    'audit_form_vl' => 'vl',
    'audit_form_eid' => 'eid',
    'audit_form_covid19' => 'covid19',
    'audit_form_tb' => 'tb',
    'audit_form_hepatitis' => 'hepatitis',
    'audit_form_generic' => 'generic',
];

try {
    $counter = 0;
    foreach ($tableToTestTypeMap as $tableName => $testType) {

        $counter++;
        // touch the lock file every 10 iterations to reduce the number of times disk is accessed
        if ($counter % 10 === 0) {
            MiscUtility::touchLockFile($lockFile);
        }

        if ($isCli) {
            echo "Processing duplicates for table: $tableName ($testType)" . PHP_EOL;
        }

        $primaryKeyColumn = TestsService::getPrimaryColumn($testType);

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
            if ($isCli) {
                echo "No duplicates found in $tableName." . PHP_EOL;
            }
            continue;
        }

        if ($isCli) {
            echo "Processing $tableName ($testType): " . count($duplicates) . " duplicate groups found." . PHP_EOL;
        }


        // Step 2: Delete duplicates using $duplicates data
        foreach ($duplicates as $group) {
            $vlSampleId = $group[$primaryKeyColumn];
            $oldestDatetime = $group['oldest_datetime'];

            $deleteQuery = "DELETE FROM $tableName
                            WHERE $primaryKeyColumn = ? AND
                            dt_datetime != ?";

            $db->rawQuery($deleteQuery, [$vlSampleId, $oldestDatetime]);
        }

        if ($isCli) {
            echo "Duplicates removed successfully for $tableName." . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    if ($isCli) {
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
