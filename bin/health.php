#!/usr/bin/env php
<?php

// Report on disk usage, MySQL responsiveness, and key application paths.
// Designed for monitoring / oncall — non-zero exit on critical thresholds.

declare(strict_types=1);

use App\Utilities\MiscUtility;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\TableSeparator;

require_once __DIR__ . '/../bootstrap.php';

// only run from command line
if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// ---------- config (tweak thresholds/paths here) ----------
$paths = [
    'logs' => LOG_PATH,
    'cache' => CACHE_PATH,
    'uploads' => UPLOAD_PATH,
    'temporary' => TEMP_PATH,
];

$diskMount = '/';
$warnDiskUsagePct = 80;
$criticalDiskUsagePct = 90;
$mysqlDegradedMs = 800;

// Off-machine backups. These three paths are written by scripts/remote-backup.sh
// and its installed runner; they are hard-coded there, so they are restated here
// rather than discovered. /etc/intelis/backup.conf is deliberately not read: it
// is mode 600 and holds the SMB credentials, and the status file already carries
// everything this check needs.
$backupStatusFile = '/var/lib/intelis/backup-status.json';
$backupRunner = '/usr/local/bin/intelis-backup.sh';
// The runner is scheduled every 8 hours, so one missed run is not yet a finding.
$backupStaleHours = 24;
$backupCriticalHours = 72;

$stateFile = CACHE_PATH . '/health_state.json';
// ---------------------------------------------------------

$output = new ConsoleOutput();

function loadState(string $path): array
{
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }
    return [];
}

function saveState(string $path, array $state): void
{
    @file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES));
}

function setStateAndMaybeAlert(
    array &$state,
    string $key,
    string $newStatus,
    callable $onChange
): void {
    $old = $state[$key] ?? 'unknown';
    if ($old !== $newStatus) {
        $onChange($old, $newStatus);
        $state[$key] = $newStatus;
    }
}

function assertWritableDir(string $dir): bool
{
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    $probe = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.wcheck';
    $ok = @file_put_contents($probe, 'x') !== false;
    if ($ok) {
        MiscUtility::deleteFile($probe);
    }
    return $ok;
}

function formatStatus(string $status): string
{
    return match ($status) {
        'ok' => '<fg=green>OK</>',
        'warn' => '<fg=yellow>WARN</>',
        'critical' => '<fg=red>CRITICAL</>',
        default => '<fg=gray>UNKNOWN</>',
    };
}

function isServiceRunning(string $serviceName): bool
{
    $output = [];
    $returnCode = 0;

    exec("systemctl is-active $serviceName 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0 && trim($output[0] ?? '') === 'active') {
        return true;
    }

    exec("pgrep -x $serviceName 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        return true;
    }

    if ($serviceName === 'mysql') {
        exec("pgrep -x mysqld 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0) {
            return true;
        }
    }

    return false;
}

// ---- Load persisted state ----
$state = loadState($stateFile);
$criticalCount = 0;
$warnCount = 0;
$okCount = 0;

// ---- 1) Directory Permissions ----
$dirResults = [];
foreach ($paths as $name => $path) {
    $key = "fs_perms:$name";
    $ok = assertWritableDir($path);
    $status = $ok ? 'ok' : 'critical';

    setStateAndMaybeAlert(
        $state,
        $key,
        $status,
        function ($old, $new) use ($name, $path): void {
            if ($new === 'critical') {
                SystemService::insertSystemAlert(
                    'error',
                    'fs_perms',
                    _translate("Directory not writable:") . ' ' . $name,
                    ['path' => $path],
                    'admin'
                );
            } elseif ($old !== 'unknown') {
                SystemService::insertSystemAlert(
                    'info',
                    'fs_perms',
                    _translate("Directory writable again:") . ' ' . $name,
                    ['path' => $path],
                    'admin'
                );
            }
        }
    );

    $dirResults[$name] = ['path' => $path, 'status' => $status];
    if ($status === 'ok') {
        $okCount++;
    } else {
        $criticalCount++;
    }
}

// ---- 2) Services Check ----
$apacheRunning = isServiceRunning('apache2') || isServiceRunning('httpd');
$mysqlRunning = isServiceRunning('mysql') || isServiceRunning('mysqld') || isServiceRunning('mariadb');

$apacheStatus = $apacheRunning ? 'ok' : 'critical';
$mysqlServiceStatus = $mysqlRunning ? 'ok' : 'critical';

if ($apacheRunning) {
    $okCount++;
} else {
    $criticalCount++;
}
if ($mysqlRunning) {
    $okCount++;
} else {
    $criticalCount++;
}

// ---- 3) Disk usage ----
$total = @disk_total_space($diskMount) ?: 0;
$free = @disk_free_space($diskMount) ?: 0;
$usedPct = $total ? (1 - ($free / $total)) * 100 : 0.0;

$diskStatus = 'ok';
$level = 'info';
if ($total > 0) {
    if ($usedPct >= $criticalDiskUsagePct) {
        $diskStatus = 'critical';
        $level = 'critical';
    } elseif ($usedPct >= $warnDiskUsagePct) {
        $diskStatus = 'warn';
        $level = 'warn';
    }
}

setStateAndMaybeAlert(
    $state,
    'disk',
    $diskStatus,
    function ($old, $new) use ($diskMount, $usedPct, $level): void {
        if ($new === 'warn' || $new === 'critical') {
            SystemService::insertSystemAlert(
                $level,
                'disk',
                sprintf(_translate('Disk usage high:') . ' %.1f%%', $usedPct),
                ['used_pct' => round($usedPct, 1), 'mount' => $diskMount],
                'admin'
            );
        } elseif ($old !== 'unknown') {
            SystemService::insertSystemAlert(
                'info',
                'disk',
                sprintf(_translate('Disk usage recovered:') . ' %.1f%%', $usedPct),
                ['used_pct' => round($usedPct, 1), 'mount' => $diskMount],
                'admin'
            );
        }
    }
);

if ($diskStatus === 'ok') {
    $okCount++;
} elseif ($diskStatus === 'warn') {
    $warnCount++;
} else {
    $criticalCount++;
}

// ---- 4) MySQL health (latency check) ----
$mysqlLatencyStatus = 'ok';
$mysqlLevel = 'info';
$latencyMs = null;
try {
    $start = microtime(true);
    $db->rawQueryOne('SELECT 1');
    $latencyMs = (microtime(true) - $start) * 1000.0;
    if ($latencyMs >= $mysqlDegradedMs) {
        $mysqlLatencyStatus = 'warn';
        $mysqlLevel = 'warn';
    }
} catch (Throwable) {
    $mysqlLatencyStatus = 'critical';
    $mysqlLevel = 'critical';
}

setStateAndMaybeAlert(
    $state,
    'mysql',
    $mysqlLatencyStatus,
    function ($old, $new) use ($mysqlLevel, $latencyMs): void {
        if ($new === 'critical') {
            SystemService::insertSystemAlert(
                'critical',
                'mysql',
                _translate('MySQL unreachable/crashed'),
                null,
                'admin'
            );
        } elseif ($new === 'warn') {
            SystemService::insertSystemAlert(
                'warn',
                'mysql',
                sprintf(_translate('MySQL latency high:') . ' ~%.0f ms', $latencyMs ?? -1),
                ['latency_ms' => $latencyMs],
                'admin'
            );
        } elseif ($old !== 'unknown') {
            SystemService::insertSystemAlert(
                'info',
                'mysql',
                _translate('MySQL recovered'),
                ['latency_ms' => $latencyMs],
                'admin'
            );
        }
    }
);

if ($mysqlLatencyStatus === 'ok') {
    $okCount++;
} elseif ($mysqlLatencyStatus === 'warn') {
    $warnCount++;
} else {
    $criticalCount++;
}

// ---- 5) Off-machine backup ----
// Reports what the backup runner already recorded; it never contacts the backup
// destination itself. A second, differently-configured connection test could
// disagree with the runner and there would be no way to tell which one was
// right — and the runner is the one whose opinion decides whether data is
// actually leaving this machine.
//
// Off-machine backup is opt-in, so an instance without it is reported as
// unknown rather than failed: it counts towards nothing and raises no alert.
// What is worth waking someone for is a backup that was set up and has since
// stopped working, because that is the case that looks fine from the outside.
$backupStatus = 'unknown';
$backupDetail = 'Not set up — run: intelis backup setup';
$backupAgeHours = null;

if (is_file($backupStatusFile) && !is_readable($backupStatusFile)) {
    // get_current_user() would answer for the owner of this file rather than the
    // account running it, which is the one that cannot read the status file.
    // Both functions come from ext-posix, which is not guaranteed to be loaded;
    // each is checked for rather than inferred from the other being present.
    $runningAs = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'this user')
        : (trim((string) @shell_exec('id -nu 2>/dev/null')) ?: 'this user');

    $backupDetail = sprintf('%s is not readable by %s', $backupStatusFile, $runningAs);
} elseif (is_file($backupStatusFile)) {
    $backupState = loadState($backupStatusFile);
    $lastSuccessEpoch = (int) ($backupState['last_success_epoch'] ?? 0);
    $lastAttempt = (string) ($backupState['status'] ?? 'unknown');
    $lastError = trim((string) ($backupState['last_error'] ?? ''));
    $destination = (string) ($backupState['destination'] ?? '');
    $dbDumpAgeHours = (int) ($backupState['db_dump_age_hours'] ?? -1);

    if ($backupState === []) {
        // The file is there and readable but did not parse — a run killed
        // part-way through writing it, or something else owns the path.
        $backupStatus = 'warn';
        $backupDetail = sprintf('%s is unreadable or not valid JSON', $backupStatusFile);
    } elseif ($lastSuccessEpoch <= 0) {
        $backupStatus = 'critical';
        $backupDetail = 'Set up, but no backup has ever finished'
            . ($lastError !== '' ? ' — ' . $lastError : '');
    } else {
        $backupAgeHours = (int) floor((time() - $lastSuccessEpoch) / 3600);

        if ($backupAgeHours >= $backupCriticalHours) {
            $backupStatus = 'critical';
        } elseif ($backupAgeHours >= $backupStaleHours || $lastAttempt === 'failed') {
            $backupStatus = 'warn';
        } else {
            $backupStatus = 'ok';
        }

        $backupDetail = sprintf(
            'Last good backup %dh ago%s',
            $backupAgeHours,
            $destination !== '' ? sprintf(' (%s)', $destination) : ''
        );

        // The runner keeps the last success and the last attempt apart, so a
        // fresh backup and a failing one are both true at once after the first
        // failure. Saying only the age would hide the failure that matters.
        if ($lastAttempt === 'failed') {
            $backupDetail .= '; last attempt FAILED' . ($lastError !== '' ? ': ' . $lastError : '');
        }

        // The files are only half a backup — the data is in the dump written by
        // the scheduled db-tools job. A current copy of a stale dump restores to
        // whenever that dump was taken.
        if ($dbDumpAgeHours > $backupStaleHours) {
            $backupDetail .= sprintf('; newest DB dump %dh old', $dbDumpAgeHours);
            if ($backupStatus === 'ok') {
                $backupStatus = 'warn';
            }
        }
    }
} elseif (is_file($backupRunner)) {
    // The runner is installed, so someone set this up, but it has never written
    // a status file — its very first run never completed.
    $backupStatus = 'critical';
    $backupDetail = 'Set up, but no backup has ever run';
}

setStateAndMaybeAlert(
    $state,
    'backup_remote',
    $backupStatus,
    function ($old, $new) use ($backupDetail, $backupAgeHours): void {
        if ($new === 'critical' || $new === 'warn') {
            SystemService::insertSystemAlert(
                $new === 'critical' ? 'critical' : 'warn',
                'backup_remote',
                _translate('Off-machine backup is not current:') . ' ' . $backupDetail,
                ['age_hours' => $backupAgeHours],
                'admin'
            );
            return;
        }

        // Recovery only. Arriving at ok from 'unknown' is either the first run
        // on this instance or an instance that has just had backups set up, and
        // neither is something recovering. 'unknown' is both this check's
        // not-set-up state and setStateAndMaybeAlert's no-previous-state
        // sentinel; the right answer happens to be the same for both.
        if ($new === 'ok' && $old !== 'unknown') {
            SystemService::insertSystemAlert(
                'info',
                'backup_remote',
                _translate('Off-machine backup is current again'),
                ['age_hours' => $backupAgeHours],
                'admin'
            );
        }
    }
);

if ($backupStatus === 'ok') {
    $okCount++;
} elseif ($backupStatus === 'warn') {
    $warnCount++;
} elseif ($backupStatus === 'critical') {
    $criticalCount++;
}

// persist state
saveState($stateFile, $state);

// ---- Display Results ----
$output->writeln('');

// Services & Disk Table
$servicesTable = new Table($output);
$servicesTable->setHeaderTitle('SERVICES & RESOURCES');
$servicesTable->setHeaders(['Service/Resource', 'Status', 'Details']);

$servicesTable->setRows([
    ['Apache', formatStatus($apacheStatus), $apacheRunning ? 'Running' : 'Not detected'],
    ['MySQL/MariaDB', formatStatus($mysqlServiceStatus), $mysqlRunning ? 'Running' : 'Not detected'],
    [
        'MySQL Latency',
        formatStatus($mysqlLatencyStatus),
        $latencyMs !== null ? sprintf('%.0f ms (threshold: %d ms)', $latencyMs, $mysqlDegradedMs) : 'N/A'
    ],
    new TableSeparator(),
    [
        'Disk Usage',
        formatStatus($diskStatus),
        sprintf('%.1f%% used (warn: %d%%, critical: %d%%)', $usedPct, $warnDiskUsagePct, $criticalDiskUsagePct)
    ],
    ['Off-machine Backup', formatStatus($backupStatus), $backupDetail],
]);

$servicesTable->render();
$output->writeln('');

// Directory Permissions Table
$dirTable = new Table($output);
$dirTable->setHeaderTitle('DIRECTORY PERMISSIONS');
$dirTable->setHeaders(['Directory', 'Path', 'Status']);

$dirRows = [];
foreach ($dirResults as $name => $info) {
    $dirRows[] = [ucfirst($name), $info['path'], formatStatus($info['status'])];
}

$dirTable->setRows($dirRows);
$dirTable->render();
$output->writeln('');

// Summary
$output->writeln(str_repeat('=', 60));
if ($criticalCount > 0) {
    $output->writeln(sprintf(
        '<fg=red;options=bold>HEALTH STATUS: %d CRITICAL</> | <fg=yellow>%d warnings</> | <fg=green>%d ok</>',
        $criticalCount,
        $warnCount,
        $okCount
    ));
} elseif ($warnCount > 0) {
    $output->writeln(sprintf(
        '<fg=yellow;options=bold>HEALTH STATUS: %d WARNINGS</> | <fg=green>%d ok</>',
        $warnCount,
        $okCount
    ));
} else {
    $output->writeln(sprintf(
        '<fg=green;options=bold>HEALTH STATUS: ALL %d CHECKS PASSED</>',
        $okCount
    ));
}
$output->writeln(str_repeat('=', 60));
