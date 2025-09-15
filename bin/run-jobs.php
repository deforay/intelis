<?php

use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once(__DIR__ . "/../bootstrap.php");

try {

    $phpPath = SYSTEM_CONFIG['system']['php_path'] ?? PHP_BINARY;

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $db->where("status = 'pending'");
    $db->where("IFNULL(scheduled_on, now()) <= now() OR IFNULL(run_once, 'no') = 'yes'");
    $scheduledJobs = $db->get('scheduled_jobs');

    if (!empty($scheduledJobs)) {
        foreach ($scheduledJobs as $job) {
            $db->update('scheduled_jobs', ['status' => "processing"], "job_id = " . $job['job_id']);
            exec($phpPath . " " . realpath(BIN_PATH) . DIRECTORY_SEPARATOR .  $job['job']);
            $db->where("job_id = " . $job['job_id']);
            $db->update('scheduled_jobs', [
                "completed_on" => DateUtility::getCurrentDateTime(),
                'run_once' => 'no',
                "status" => "completed"
            ]);
        }
    }
} catch (Exception $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
