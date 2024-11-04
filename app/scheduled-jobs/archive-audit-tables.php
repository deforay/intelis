#!/usr/bin/env php
<?php

require_once(__DIR__ . "/../../bootstrap.php");

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$cliMode = CommonService::isCliRequest();
$lockFile = MiscUtility::getLockFile(__FILE__);

if (MiscUtility::fileExists($lockFile) && !MiscUtility::isLockFileExpired($lockFile, maxAgeInSeconds: 18000)) {
    if ($cliMode) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

// Functions for metadata management
function loadMetadata($path)
{
    if (file_exists($path)) {
        return json_decode(file_get_contents($path), true);
    }
    return [];
}

function saveMetadata($path, $metadata)
{
    file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT));
}

function updateLastProcessedDate(&$metadata, $tableName, $lastProcessedDate)
{
    $metadata[$tableName]['last_processed_date'] = $lastProcessedDate;
}

// Archiving logic
try {
    $metadataPath = ROOT_PATH . DIRECTORY_SEPARATOR . "metadata" . DIRECTORY_SEPARATOR . "archive.mdata.json";
    MiscUtility::makeDirectory(dirname($metadataPath));
    $metadata = loadMetadata($metadataPath);  // Load metadata once

    $tableToTestTypeMap = [
        'audit_form_vl' => 'vl',
        'audit_form_eid' => 'eid',
        'audit_form_covid19' => 'covid19',
        'audit_form_tb' => 'tb',
        'audit_form_hepatitis' => 'hepatitis',
        'audit_form_generic' => 'generic',
    ];

    $archiveBeforeDate = DateUtility::getDateBeforeMonths(1);
    $mainDbName = SYSTEM_CONFIG['database']['db'];
    $archivePath = ROOT_PATH . "/audit-trail";

    MiscUtility::makeDirectory($archivePath);

    function getCurrentColumns($db, $tableName)
    {
        $columns = [];
        $result = $db->rawQuery("SHOW COLUMNS FROM $tableName");
        foreach ($result as $row) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }

    function getCsvHeaders($filePath)
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $fileHandle = gzopen($filePath, 'r');
        $headers = fgetcsv($fileHandle);
        gzclose($fileHandle);
        return $headers;
    }

    function ensureCorrectHeaders($filePath, $currentHeaders)
    {
        $existingHeaders = getCsvHeaders($filePath);
        if ($existingHeaders !== $currentHeaders) {
            $tempFile = "$filePath.tmp";
            $tempHandle = gzopen($tempFile, 'w');
            if ($tempHandle === false) {
                LoggerUtility::logError("Failed to open temporary file for writing headers.");
                return;
            }
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
        $lastProcessedDate = $metadata[$auditTable]['last_processed_date'] ?? null;

        if ($cliMode) {
            echo "Archiving data from $mainDbName.$auditTable to compressed CSV files for test type $testType.." . PHP_EOL;
        }

        $limit = 1000;
        $offset = 0;

        do {
            if ($lastProcessedDate) {
                $db->connection('default')->where('dt_datetime', $lastProcessedDate, '>');
            }
            $db->connection('default')->orderBy('dt_datetime', 'asc');
            $dataToArchive = $db->connection('default')->get($auditTable, [$offset, $limit]);
            $rowCount = count($dataToArchive);

            if ($cliMode) {
                echo "Fetched $rowCount rows\n";
            }

            if ($rowCount > 0) {
                $dataAppended = false;
                foreach ($dataToArchive as $record) {
                    $uniqueId = $record['unique_id'];
                    MiscUtility::makeDirectory("$archivePath/$testType");
                    $filePath = "$archivePath/$testType/{$uniqueId}.csv.gz";

                    $currentHeaders = getCurrentColumns($db, $auditTable);
                    ensureCorrectHeaders($filePath, $currentHeaders);

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

                    $dataAppended = true;
                }

                if ($dataAppended) {
                    $lastProcessedDateInBatch = end($dataToArchive)['dt_datetime'];
                    updateLastProcessedDate($metadata, $auditTable, $lastProcessedDateInBatch);
                }

                $offset += $limit;
                if ($cliMode) {
                    echo "Processed $rowCount rows, moving to next batch.." . PHP_EOL;
                }
            } else {
                if ($cliMode) {
                    echo "No more data to process in $auditTable" . PHP_EOL;
                }
            }
        } while ($rowCount > 0);

        saveMetadata($metadataPath, $metadata);  // Save metadata after each table loop
    }

    if ($cliMode) {
        echo "Archiving process completed" . PHP_EOL;
    }
} catch (Exception $e) {
    if ($cliMode) {
        echo "Some or all data could not be archived" . PHP_EOL;
        echo $e->getMessage() . PHP_EOL;
        echo $db->getLastQuery();
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
