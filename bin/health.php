<?php
// bin/health.php
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
