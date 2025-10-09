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

$smartConnectURL = $general->getGlobalConfig('vldashboard_url');

$remoteURL = $general->getRemoteURL();

$timeZone = $_SESSION['APP_TIMEZONE'];

$schedule = new Schedule();

# Touch heartbeat file if Crunz runs
touch(ROOT_PATH . DIRECTORY_SEPARATOR . ".cron_heartbeat");

// Archive Data from Audit Tables
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/archive-audit-tables.php")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Archiving Audit Tables');

// Generate Sample IDs
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/sample-code-generator.php")
    ->everyMinute()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Generating sample codes');


// === DB-TOOLS SCHEDULES ===

// DB Backup
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/db-tools.php backup")
    ->everySixHours()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('DB Tools: backup of both databases every 6 hours');

// Daily binlog purge
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/db-tools.php purge-binlogs --days=7")
    ->cron('5 4 * * *') // 04:05 am daily
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('DB Tools: purge MySQL binary logs older than 7 days');

// Weekly maintenance
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/db-tools.php maintain --days=7")
    ->cron('0 3 * * 0') // 03:00 am Sundays
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('DB Tools: weekly mysqlcheck (repair/optimize/analyze) + binlog purge');


// Weekly config backup    
$schedule->run(PHP_BINARY . ' ' . BIN_PATH . '/backup-configs.php')
    ->weeklyOn(0, '03:00') // Sundays 3:00 AM
    ->description('Weekly config backup')
    ->preventOverlapping()
    ->timezone($timeZone);


// Cleanup Old Files
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/cleanup.php")
    ->cron('45 0 * * *')
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Cleaning Up Old Backups and Temporary files');

// Expiring/Locking Samples
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/update-sample-status.php")
    ->cron('5 0 * * *')
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Updating sample status to Expired or Locking samples');

// MACHINE INTERFACING
if (!empty(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled'] === true) {
    // Syncing data from SQLite to MySQL
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/sync-interface-sqlite-mysql.php")
        ->everyFiveMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Importing data from sqlite db into mysql db');

    // Importing data from interface db into lis db`
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/interface.php")
        ->everyMinute()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Importing data from interface db into local db');
}

// UPDATE VL RESULT INTERPRETATION
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/update-vl-suppression.php")
    ->everyMinute()
    ->timezone($timeZone)
    ->preventOverlapping()
    ->description('Updating VL Result Interpretation');


// REMOTE SYNC JOBS START
if (!empty($general->getRemoteURL()) && $general->isLISInstance() === true) {
    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/remote/sts-metadata-receiver.php")
        ->everyFiveMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing metadata from STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/remote/requests-receiver.php")
        ->everyFifteenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing requests from STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/remote/results-sender.php")
        ->everyTenMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to STS');

    $schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/remote/lab-metadata-sender.php")
        ->everyThirtyMinutes()
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing results to STS');
}
// REMOTE SYNC JOBS END



// Smart-Connect DASHBOARD JOBS START

if (!empty($smartConnectURL)) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/metadata.php")
        ->cron('*/20 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VLSM Reference data from local database to Dashboard');
}


if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/vl.php")
        ->cron('*/25 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing VL data from local database to Dashboard');
}

if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/eid.php")
        ->cron('*/30 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing EID data from local database to Dashboard');
}
if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/covid19.php")
        ->cron('*/35 * * * *')
        ->timezone($timeZone)
        ->preventOverlapping()
        ->description('Syncing Covid-19 data from local database to Dashboard');
}
// DASHBOARD JOBS END

return $schedule;
