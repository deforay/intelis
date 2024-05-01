<?php

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

require_once(__DIR__ . "/../../bootstrap.php");

try {

    $phpPath = SYSTEM_CONFIG['system']['php_path'] ?? PHP_BINARY;

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $db->where("status = 'pending'");
    $db->where("IFNULL(scheduled_on, now()) <= now() OR IFNULL(run_once, 'no') = 'yes'");
    $scheduledJobs = $db->get('scheduled_jobs');

    if (!empty($scheduledJobs)) {
        foreach ($scheduledJobs as $job) {
            $db->update('scheduled_jobs', array('status' => "processing"), "job_id = " . $job['job_id']);
            exec($phpPath . " " . realpath(APPLICATION_PATH . "/scheduled-jobs") . DIRECTORY_SEPARATOR .  $job['job']);
            $db->where("job_id = " . $job['job_id']);
            $db->update('scheduled_jobs', [
                "completed_on" => DateUtility::getCurrentDateTime(),
                'run_once' => 'no',
                "status" => "completed"
            ]);
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/run-jobs.php');
}
