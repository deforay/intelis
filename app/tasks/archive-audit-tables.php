<?php

require_once __DIR__ . "/../../bootstrap.php";

declare(ticks=1);

use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$sampleCode = null;
if (!empty($_GET)) {
    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_GET = _sanitizeInput($request->getQueryParams());
    if (!empty($_GET['sampleCode'])) {
        $sampleCode = $_GET['sampleCode'];
    }
}


$cliMode = php_sapi_name() === 'cli';
$lockFile = MiscUtility::getLockFile(__FILE__);

if (!MiscUtility::isLockFileExpired($lockFile)) {
    if ($cliMode) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

// Updates metadata for last processed date
function updateLastProcessedDate(&$metadata, $tableName, $lastProcessedDate)
{
    $metadata[$tableName]['last_processed_date'] = $lastProcessedDate;
}

// Generator function to retrieve records in batches
function fetchRecords(DatabaseService $db, string $tableName, ?string $lastProcessedDate = null, int $limit = 1000, $sampleCode = null)
{
    $offset = 0;
    while (true) {
        if (!empty($sampleCode)) {
            $db->connection('default')->where("sample_code = '$sampleCode' OR remote_sample_code = '$sampleCode' OR external_sample_code = '$sampleCode'");
        } elseif ($lastProcessedDate) {
            $db->connection('default')->where('dt_datetime', $lastProcessedDate, '>');
        }
        $db->connection('default')->orderBy('dt_datetime', 'asc');
        $batch = $db->connection('default')->get($tableName, [$offset, $limit]);

        $rowCount = count($batch);
        if ($rowCount === 0) {
            break;
        }

        foreach ($batch as $record) {
            yield $record;
        }

        $offset += $limit;
    }
}

try {
    $metadataPath = ROOT_PATH . DIRECTORY_SEPARATOR . "metadata" . DIRECTORY_SEPARATOR . "archive.mdata.json";
    $metadata = empty($sampleCode) ? MiscUtility::loadMetadata($metadataPath) : [];

    $tableToTestTypeMap = [
        'audit_form_vl' => 'vl',
        'audit_form_eid' => 'eid',
        'audit_form_covid19' => 'covid19',
        'audit_form_tb' => 'tb',
        'audit_form_hepatitis' => 'hepatitis',
        'audit_form_generic' => 'generic',
    ];

    $archivePath = ROOT_PATH . "/audit-trail";
    MiscUtility::makeDirectory($archivePath);

    // Helper functions for managing CSV headers and revisions
    function getCurrentColumns($db, $tableName)
    {
        $columns = [];
        $result = $db->rawQuery("SHOW COLUMNS FROM $tableName");
        foreach ($result as $row) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }

    function ensureCorrectHeaders($filePath, $currentHeaders)
    {
        $existingHeaders = [];
        if (file_exists($filePath)) {
            $fileHandle = gzopen($filePath, 'r');
            $existingHeaders = fgetcsv($fileHandle);
            gzclose($fileHandle);
        }

        if ($existingHeaders !== $currentHeaders) {
            $tempFile = "$filePath.tmp";
            $tempHandle = gzopen($tempFile, 'w');
            gzwrite($tempHandle, implode(',', $currentHeaders) . "\n");

            if (file_exists($filePath)) {
                $oldFileHandle = gzopen($filePath, 'r');
                fgetcsv($oldFileHandle); // Skip old headers
                while (($row = fgetcsv($oldFileHandle)) !== false) {
                    $mappedRow = [];
                    foreach ($currentHeaders as $header) {
                        $mappedRow[] = in_array($header, $existingHeaders) ? json_encode($row[array_search($header, $existingHeaders)]) : "null";
                    }
                    gzwrite($tempHandle, implode(',', $mappedRow) . "\n");
                }
                gzclose($oldFileHandle);
            }

            gzclose($tempHandle);
            rename($tempFile, $filePath);
        }
    }

    // Load dt_datetime values from existing CSV to check for duplicates
    function loadExistingDatetimes($filePath)
    {
        $existingDatetimes = [];
        if (file_exists($filePath)) {
            $fileHandle = gzopen($filePath, 'r');
            fgetcsv($fileHandle); // Skip header row
            while (($row = fgetcsv($fileHandle)) !== false) {
                $existingDatetimes[] = $row[2]; // Assuming dt_datetime is the third column
            }
            gzclose($fileHandle);
        }
        return $existingDatetimes;
    }



    function getLastRevisionNumber($filePath)
    {
        if (!file_exists($filePath)) {
            return 0;
        }
        $lastRevision = 0;
        $fileHandle = gzopen($filePath, 'r');
        if ($fileHandle) {
            fgetcsv($fileHandle);
            while (($row = fgetcsv($fileHandle)) !== false) {
                $lastRevision = max($lastRevision, (int)$row[1]);
            }
            gzclose($fileHandle);
        }
        return $lastRevision;
    }

    foreach ($tableToTestTypeMap as $auditTable => $testType) {
        $lastProcessedDate = $sampleCode ? null : ($metadata[$auditTable]['last_processed_date'] ?? null);

        if ($cliMode) {
            echo "Archiving data from {$auditTable} for test type {$testType}.." . PHP_EOL;
        }

        $currentHeaders = getCurrentColumns($db, $auditTable);

        $counter = 0;
        foreach (fetchRecords($db, $auditTable, $lastProcessedDate, 1000, $sampleCode) as $record) {

            $counter++;
            // touch the lock file every 10 iterations to reduce the number of times disk is accessed
            if ($counter % 10 === 0) {
                MiscUtility::touchLockFile($lockFile);
            }
            $uniqueId = $record['unique_id'];
            MiscUtility::makeDirectory("$archivePath/$testType");
            $filePath = "$archivePath/$testType/{$uniqueId}.csv.gz";

            ensureCorrectHeaders($filePath, $currentHeaders);

            // Load existing dt_datetime values for duplicate check
            $existingDatetimes = loadExistingDatetimes($filePath);

            if (in_array($record['dt_datetime'], $existingDatetimes)) {
                if ($cliMode) {
                    echo "Skipping duplicate record with dt_datetime: {$record['dt_datetime']}" . PHP_EOL;
                }
                continue;
            }

            $lastRevision = getLastRevisionNumber($filePath);
            $record['revision'] = $lastRevision + 1;

            $fileHandle = gzopen($filePath, 'a');
            if ($fileHandle === false) {
                LoggerUtility::logError("Failed to open or create file $filePath for writing.");
                continue;
            }

            $rowToWrite = [];
            foreach ($currentHeaders as $header) {
                $rowToWrite[] = array_key_exists($header, $record) ? json_encode($record[$header]) : "null";
            }
            gzwrite($fileHandle, implode(',', $rowToWrite) . "\n");
            gzclose($fileHandle);

            // Update metadata only if sampleCode is not provided (bulk case)
            if (!$sampleCode) {
                $lastProcessedDate = $record['dt_datetime'];
                updateLastProcessedDate($metadata, $auditTable, $lastProcessedDate);
                MiscUtility::saveMetadata($metadataPath, $metadata);
            }
        }

        if ($cliMode) {
            echo "Completed archiving for {$auditTable}." . PHP_EOL;
        }
    }
    if ($cliMode) {
        echo "Archiving process completed." . PHP_EOL;
    }
} catch (Exception $e) {
    if ($cliMode) {
        echo "Some or all data could not be archived" . PHP_EOL;
        echo "An internal error occurred. Please check the logs." . PHP_EOL;
    }
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile(__FILE__);
}
