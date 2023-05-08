<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use Crunz\Schedule;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$vldashboardUrl = $general->getGlobalConfig('vldashboard_url');

$timeZone = $_SESSION['APP_TIMEZONE'];

$schedule = new Schedule();

// DB Backup
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/db-backups.php")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Backing Up Database');


// Cleanup Old Files
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/cleanup.php")
    ->cron('0 */12 * * *')
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Cleaning Up Old Backups and Temporary files');

// Expiring/Locking Samples
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/update-sample-status.php")
    ->everySixHours()
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
if (!empty(SYSTEM_CONFIG['remoteURL'])) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/commonDataSync.php")
        ->everyFiveMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing common/reference data from remote system');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/requestsSync.php")
        ->everyFifteenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing requests from remote system');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/remote/resultsSync.php")
        ->everyTenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to remote system');
}
// REMOTE SYNC JOBS END



// DASHBOARD JOBS START

if (!empty($vldashboardUrl)) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-reference-tables.php")
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
