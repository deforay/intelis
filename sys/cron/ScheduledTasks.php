<?php

use Crunz\Schedule;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once __DIR__ . '/../../bootstrap.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$smartConnectURL = $general->getGlobalConfig('vldashboard_url');

$remoteURL = $general->getRemoteURL();

$timezone = $_SESSION['APP_TIMEZONE'] ?? date_default_timezone_get();

$schedule = new Schedule();

# Touch heartbeat file if Crunz runs
touch(VAR_PATH . DIRECTORY_SEPARATOR . ".cron_heartbeat");

// Archive Data from Audit Tables
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/archive-audit-tables.php")
    ->everySixHours()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Archiving Audit Tables');

// Roll settled per-sample audit files into one archive per month.
//
// The archiving task above writes one file per sample, which is right for
// reading and expensive for everything else: a working lab ends up with a file
// per sample it has ever run, and audit-trail alone reaches gigabytes — much of
// it the unused tail of a filesystem block, repeated per sample. It also makes
// every off-machine backup stat hundreds of thousands of files to conclude
// there is nothing to do.
//
// Daily, and batched, because the first run on an established lab has the
// whole history to work through.
//
// 05:15 rather than midnight, and the time matters more than it looks. This
// job deletes and rewrites a great many files under var/audit-trail, and at
// midnight it would be doing that while two other things read the same tree:
// db-tools backup runs on the six-hourly boundary, and the off-machine backup
// runner is on 0 */8. rsync copying a directory that is being restructured
// beneath it reports files that still differ and re-sends work the next run
// undoes.
//
// 05:15 clears both, and also clears the monthly optimize at 04:00 on the 1st,
// which rebuilds tables and is the one neighbour here that can run long. The
// tree is then settled well before the 08:00 backup.
$schedule->run(PHP_BINARY . " " . APPLICATION_PATH . "/tasks/bundle-audit-trail.php")
    ->cron('15 5 * * *') // 05:15 daily, clear of 0 */6, 0 */8 and the monthly optimize
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Bundling settled audit files');

// Generate Sample IDs
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/sample-code-generator.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Generating sample codes');


// === DB-TOOLS SCHEDULES ===

// DB Backup
$schedule->run(PHP_BINARY . " " . VENDOR_BIN . "/db-tools backup --all")
    ->everySixHours()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('DB Tools: backup of both databases every 6 hours');

// Daily binlog purge
$schedule->run(PHP_BINARY . " " . VENDOR_BIN . "/db-tools purge-binlogs --days=7")
    ->cron('5 4 * * *') // 04:05 am daily
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('DB Tools: purge MySQL binary logs older than 7 days');

// Weekly maintenance
$schedule->run(PHP_BINARY . " " . VENDOR_BIN . "/db-tools maintain --all --days=7")
    ->cron('0 3 * * 0') // 03:00 am Sundays
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('DB Tools: weekly mysqlcheck (repair/optimize/analyze) + binlog purge');

// Monthly deep maintenance (analyze + optimize + purge binlogs)
$schedule->run(PHP_BINARY . " " . VENDOR_BIN . "/db-tools maintain --all --optimize --days=7")
    ->cron('0 4 1 * *') // 04:00 am on 1st of each month
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('DB Tools: monthly optimize + analyze + binlog purge');


// Weekly config backup    
$schedule->run(PHP_BINARY . ' ' . BIN_PATH . '/backup-configs.php')
    ->weeklyOn(0, '03:00') // Sundays 3:00 AM
    ->description('Weekly config backup')
    ->preventOverlapping()
    ->timezone($timezone);


// Housekeeping — prune old backups, temporary files, logs, and stale DB rows
$schedule->run(PHP_BINARY . ' ' . BIN_PATH . '/housekeeping.php 30')
    ->cron('45 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Housekeeping: prune old backups, temp files, and stale rows');

// System health — disk, MySQL, writable paths, and off-machine backups.
// It raises an admin alert only when a check changes state, so an instance that
// stays healthy writes nothing and one that stays broken alerts once rather than
// every hour. Off-machine backup staleness in particular has no other way of
// being noticed: the backup runner records its own failures on the machine, and
// until now nothing ever read them back.
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/health.php")
    ->hourly()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('System health checks and admin alerts');

// Expiring/Locking Samples
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/update-sample-status.php")
    ->cron('5 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Updating sample status to Expired or Locking samples');

// Flagging records whose own columns contradict each other. Counts only, never
// corrects: every contradiction it looks for has had its cause closed, so a
// count above zero means a new write path has opened one, and a job that
// quietly repaired them would erase the evidence saying so. Runs after the
// status updates above, so it sees the day's final state.
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/flag-data-issues.php")
    ->cron('25 0 * * *')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Flagging contradictory records for the Needs attention card');

// MACHINE INTERFACING
if (!empty(SYSTEM_CONFIG['interfacing']['enabled']) && SYSTEM_CONFIG['interfacing']['enabled'] === true) {
    // Syncing data from SQLite to MySQL
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/sync-interface-sqlite-mysql.php")
        ->everyFiveMinutes()
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Importing data from sqlite db into mysql db');

    // Importing data from interface db into lis db`
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/interface.php")
        ->everyMinute()
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Importing data from interface db into local db');
}

// UPDATE VL RESULT INTERPRETATION
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/update-vl-suppression.php")
    ->everyMinute()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Updating VL Result Interpretation');


// REMOTE SYNC JOBS START
if (!empty($general->getRemoteURL()) && $general->isLISInstance() === true) {
    $schedule->run('COMPOSER_ALLOW_SUPERUSER=1 composer -d ' . ROOT_PATH . ' run sync-sts -n')
        ->everyFiveMinutes()
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Syncing data to and from STS');
}
// REMOTE SYNC JOBS END

// Remote command plane retention — runs on both STS + LIS (cheap no-op on LIS).
// Deletes terminal rows in s_lis_remote_commands older than
// global_config.remote_command_retention_days (default 90).
$schedule->run(PHP_BINARY . " " . BIN_PATH . "/prune-remote-commands.php")
    ->cron('15 3 * * *') // 03:15 daily
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Pruning old rows from s_lis_remote_commands');



// Smart-Connect DASHBOARD JOBS START

if (!empty($smartConnectURL)) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/sync.php metadata")
        ->cron('*/20 * * * *')
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Syncing VLSM Reference data from local database to Dashboard');
}


if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/sync.php vl")
        ->cron('*/25 * * * *')
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Syncing VL data from local database to Dashboard');
}

if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/sync.php eid")
        ->cron('*/30 * * * *')
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Syncing EID data from local database to Dashboard');
}
if (!empty($smartConnectURL) && !empty(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/smart-connect/sync.php covid19")
        ->cron('*/35 * * * *')
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Syncing Covid-19 data from local database to Dashboard');
}
// DASHBOARD JOBS END


// Module specific scheduled tasks
// Inter-lab referrals run on STS instances; the script self-loops over every
// active test module that supports referrals.
if ($general->isSTSInstance()) {
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/referrals.php")
        ->everyMinute()
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Updating inter-lab referrals and referral history across active test modules');

    // Rejection reason ids are minted per install, so a reason a lab creates from
    // the "Other" box arrives here pointing at nothing. Labs send their reason
    // tables up with every metadata sync and the receiver records what each of
    // their ids means; this puts that answer onto the samples already stored.
    //
    // Scheduled rather than run once at upgrade: the answers arrive over weeks, as
    // each lab upgrades and syncs, so there is no single moment when the work is
    // complete. Idempotent -- it only touches rows still holding a lab's own id --
    // and --quiet so the nightly run is silent unless it actually rewrote something.
    $schedule->run(PHP_BINARY . " " . BIN_PATH . "/apply-rejection-reason-map.php --quiet")
        ->cron('40 3 * * *') // 03:40 daily, after the retention prune at 03:15
        ->timezone($timezone)
        ->preventOverlapping()
        ->description('Applying labs\' rejection reason ids to samples already stored');
}


return $schedule;
