<?php
// bin/health.php
declare(strict_types=1);

use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    echo "Run from CLI\n";
    exit(1);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// ---------- config (tweak thresholds/paths here) ----------
$paths = [
    'logs'      => LOG_PATH,
    'cache'     => CACHE_PATH,
    'uploads'   => UPLOAD_PATH,
    'temporary' => TEMP_PATH,
];

$diskMount             = '/';
$warnDiskUsagePct      = 80;   // warn at 80%
$criticalDiskUsagePct  = 90;   // critical at 90%
$mysqlDegradedMs       = 800;  // warn if ping slower than this

$stateFile = CACHE_PATH . '/health_state.json'; // persisted last states
// ---------------------------------------------------------

function loadState(string $path): array
{
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) return $data;
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
    string $newStatus,                   // e.g. ok|warn|critical
    callable $onChange                   // function($oldStatus, $newStatus): void
): void {
    $old = $state[$key] ?? 'unknown';
    if ($old !== $newStatus) {
        $onChange($old, $newStatus);
        $state[$key] = $newStatus;
    }
}

function assertWritableDir(string $dir): bool
{
    if (!is_dir($dir) || !is_writable($dir)) return false;
    $probe = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.wcheck';
    $ok = @file_put_contents($probe, 'x') !== false;
    if ($ok) @unlink($probe);
    return $ok;
}

// ---- 1) Folder permissions (apache/web user) ----
$state = loadState($stateFile);

foreach ($paths as $name => $path) {
    $key = "fs_perms:$name";
    $ok  = assertWritableDir($path);
    setStateAndMaybeAlert(
        $state,
        $key,
        $ok ? 'ok' : 'critical',
        function ($old, $new) use ($name, $path) {
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
}

// ---- 2) Disk usage ----
$total = @disk_total_space($diskMount) ?: 0;
$free  = @disk_free_space($diskMount)  ?: 0;
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
    function ($old, $new) use ($diskMount, $usedPct, $level) {
        if ($new === 'warn' || $new === 'critical') {
            SystemService::insertSystemAlert(
                $level,
                'disk',
                sprintf(_translate('Disk usage high:') . ' %.1f%%', $usedPct, $diskMount),
                ['used_pct' => round($usedPct, 1), 'mount' => $diskMount],
                'admin'
            );
        } elseif ($old !== 'unknown') {
            SystemService::insertSystemAlert(
                'info',
                'disk',
                sprintf(_translate('Disk usage recovered:') . ' %.1f%%', $usedPct, $diskMount),
                ['used_pct' => round($usedPct, 1), 'mount' => $diskMount],
                'admin'
            );
        }
    }
);

// ---- 3) MySQL health (stopped or degraded) ----
$mysqlStatus = 'ok';
$mysqlLevel  = 'info';
$latencyMs   = null;
try {
    $start = microtime(true);
    // simple ping
    $db->rawQueryOne('SELECT 1');
    $latencyMs = (microtime(true) - $start) * 1000.0;
    if ($latencyMs >= $mysqlDegradedMs) {
        $mysqlStatus = 'warn';
        $mysqlLevel  = 'warn';
    }
} catch (Throwable $e) {
    $mysqlStatus = 'critical';
    $mysqlLevel  = 'critical';
}

setStateAndMaybeAlert(
    $state,
    'mysql',
    $mysqlStatus,
    function ($old, $new) use ($mysqlLevel, $latencyMs) {
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

// persist last seen states
saveState($stateFile, $state);

// optional: echo summary for CLI
printf(
    "health: fs_perms ok=%s, disk=%.1f%% (%s), mysql=%s%s\n",
    json_encode(array_map(
        fn($k) => ($state["fs_perms:$k"] ?? 'unknown'),
        array_keys($paths)
    )),
    $usedPct,
    $state['disk'] ?? 'unknown',
    $state['mysql'] ?? 'unknown',
    isset($latencyMs) ? sprintf(" (~%.0fms)", $latencyMs) : ''
);
