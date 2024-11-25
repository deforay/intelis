<?php

use Crunz\Schedule;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once __DIR__ . '/../bootstrap.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

$remoteURL = $general->getRemoteURL();

$timeZone = $_SESSION['APP_TIMEZONE'];

$schedule = new Schedule();


// Archive Data from Audit Tables
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/archive-audit-tables.php")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Archiving Audit Tables');

// Generate Sample Codes
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/sample-code-generator.php")
    ->everyMinute()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Generating sample codes');

// DB Backup
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/db-backups.php")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Backing Up Database');


// Cleanup Old Files
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/cleanup.php")
    ->cron('45 0 * * *')
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Cleaning Up Old Backups and Temporary files');

// Expiring/Locking Samples
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/update-sample-status.php")
    ->cron('5 0 * * *')
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Updating sample status to Expired or Locking samples');

// MACHINE INTERFACING
if (!empty(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled'] === true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/interface.php")
        ->everyMinute()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Importing data from interface db into local db');
}

// UPDATE VL RESULT INTERPRETATION
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/update-vl-suppression.php")
    ->everyMinute()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Updating VL Result Interpretation');


// REMOTE SYNC JOBS START
if (!empty($general->getRemoteURL())) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/sts-metadata-receiver.php")
        ->everyFiveMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing metadata from STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/requests-receiver.php")
        ->everyFifteenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing requests from STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/results-sender.php")
        ->everyTenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/lab-metadata-sender.php")
        ->everyThirtyMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to STS');
}
// REMOTE SYNC JOBS END



// DASHBOARD JOBS START

if (!empty($vldashboardUrl)) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-metadata.php")
        ->cron('*/20 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VLSM Reference data from local database to Dashboard');
}


if (!empty($vldashboardUrl) && !empty(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-vl.php")
        ->cron('*/25 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VL data from local database to Dashboard');
}

if (!empty($vldashboardUrl) && !empty(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-eid.php")
        ->cron('*/30 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing EID data from local database to Dashboard');
}
if (!empty($vldashboardUrl) && !empty(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-covid19.php")
        ->cron('*/35 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing Covid-19 data from local database to Dashboard');
}
// DASHBOARD JOBS END

return $schedule;
