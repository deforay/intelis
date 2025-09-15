#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$defaultDuration = (isset($argv[1]) && is_numeric($argv[1])) ? (int)$argv[1] : 30;

// Directory specific durations in days (key-value pairs)
$cleanup = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups' => null, // for null values, the default duration will be used
    ROOT_PATH . DIRECTORY_SEPARATOR . 'logs' => null, // for null values, the default duration will be used
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' => 7,
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' => 365,
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responsess' => 365,
    // Adjust or add more directories and their durations here
];

foreach ($cleanup as $folder => $duration) {
    // Determine the duration for the current directory, or use the default
    $durationToDelete = ($duration ?? $defaultDuration) * 86400; // Convert days to seconds

    if (file_exists($folder) && is_dir($folder)) {
        foreach (new DirectoryIterator($folder) as $fileInfo) {
            // Skip .htaccess or index.php
            if ($fileInfo->getFilename() === '.htaccess' || $fileInfo->getFilename() === 'index.php') {
                continue;
            }

            if (!$fileInfo->isDot() && (time() - $fileInfo->getCTime() >= $durationToDelete)) {
                if ($fileInfo->isFile() || $fileInfo->isLink()) {
                    unlink($fileInfo->getRealPath());
                } elseif ($fileInfo->isDir()) {
                    MiscUtility::removeDirectory($fileInfo->getRealPath());
                }
            }
        }
    }
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// tables and conditions to cleanup
$tablesToCleanup = [
    'activity_log' => 'date_time < NOW() - INTERVAL 365 DAY',
    'user_login_history' => 'login_attempted_datetime < NOW() - INTERVAL 365 DAY',
    'track_api_requests' => 'requested_on < NOW() - INTERVAL 365 DAY',
];

foreach ($tablesToCleanup as $table => $condition) {
    $db->where($condition);
    if (!$db->delete($table)) {
        LoggerUtility::log('error', "Error deleting from {$table}: " . $db->getLastError());
    }
}


/** Cleanup Audit Tables */

// Load metadata JSON to get last_processed_date for each table
$metadataPath = ROOT_PATH . DIRECTORY_SEPARATOR . "metadata" . DIRECTORY_SEPARATOR . "archive.mdata.json";
$metadata = json_decode(file_get_contents($metadataPath), true);

$tablesToCleanup = [
    'audit_form_vl' => 'dt_datetime',
    'audit_form_eid' => 'dt_datetime',
    'audit_form_covid19' => 'dt_datetime',
    'audit_form_tb' => 'dt_datetime',
    'audit_form_hepatitis' => 'dt_datetime',
    'audit_form_generic' => 'dt_datetime',
];

foreach ($tablesToCleanup as $table => $dateColumn) {
    if (!empty($metadata[$table]['last_processed_date'])) {
        $lastProcessedDate = $metadata[$table]['last_processed_date'];

        // Calculate the date 3 days before the last processed date
        $dateToDeleteBefore = date('Y-m-d H:i:s', strtotime($lastProcessedDate . ' - 3 days'));

        // Delete records older than (last_processed_date - 3 days)
        $db->where("{$dateColumn} < ?", [$dateToDeleteBefore]);
        if (!$db->delete($table)) {
            LoggerUtility::log('error', "Error deleting from {$table}: " . $db->getLastError());
        } else {
            LoggerUtility::log('info', "Deleted records from {$table} where {$dateColumn} < {$dateToDeleteBefore}");
        }
    }
}
