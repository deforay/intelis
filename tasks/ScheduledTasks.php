<?php

require_once(__DIR__ . "/../startup.php");

//$logpath = APPLICATION_PATH . '/logs/scheduled-jobs.log';

$general = new \Vlsm\Models\General();
$vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
$timeZone = $general->getGlobalConfig('default_time_zone');
$timeZone = !empty($timeZone) ? $timeZone : 'UTC';

date_default_timezone_set($timeZone);

$schedule = new \Crunz\Schedule();

// Expiring/Locking Samples
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/remote/scheduled-jobs/update-sample-status.php")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Updating sample status to Expired or Locking samples');



// MACHINE INTERFACING
if (!empty($interfaceConfig['enabled']) && $interfaceConfig['enabled'] == true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/interface.php")
        ->everyMinute()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Importing data from interface db into local db');
}



// REMOTE SYNC JOBS START
if (!empty($systemConfig['remoteURL'])) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/remote/scheduled-jobs/syncCommonData.php")
        ->cron("0 */30 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing common/reference data from remote system');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/remote/scheduled-jobs/syncRequests.php")
        ->cron("0 */35 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing requests from remote system');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/remote/scheduled-jobs/syncResults.php")
        ->everyFifteenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to remote system');
}
// REMOTE SYNC JOBS END



// DASHBOARD JOBS START

if (!empty($vldashboardUrl)) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-reference-tables.php")
        ->cron("0 */30 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VLSM Reference data from local database to Dashboard');
}


if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-vl.php")
        ->cron("0 */35 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VL data from local database to Dashboard');
}

if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-eid.php")
        ->cron("0 */40 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing EID data from local database to Dashboard');
}
if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/scheduled-jobs/vldashboard/vldashboard-covid19.php")
        ->cron("0 */45 * * *")
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing Covid-19 data from local database to Dashboard');
}
// DASHBOARD JOBS END

return $schedule;
