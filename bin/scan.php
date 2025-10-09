<?php

//bin/scan.php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\TableSeparator;

require_once __DIR__ . "/../bootstrap.php";

// Capture original PHP settings before we modify them
$originalMemoryLimit = ini_get('memory_limit');
$originalMaxExecutionTime = ini_get('max_execution_time');

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$cliMode = php_sapi_name() === 'cli';

if (!$cliMode) {
    echo "This script can only be run from command line." . PHP_EOL;
    exit(1);
}

/**
 * Function to format datetime or show "Never" if null
 */
function formatDateTime($datetime)
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'Never';
    }
    return DateUtility::humanReadableDateFormat($datetime, true);
}
/**
 * Normalize truthy/falsey config values to bool.
 * Accepts: true/false, 1/0, "1"/"0", "true"/"false", "on"/"off", "yes"/"no", "".
 */
function asBool(mixed $v): bool
{
    if (is_bool($v)) return $v;
    if (is_int($v))  return $v !== 0;
    $s = strtolower(trim((string)$v));
    if ($s === '' || $s === '0' || $s === 'false' || $s === 'off' || $s === 'no') return false;
    return true;
}

/**
 * Show the REAL value as the label ([ON]/[OFF]),
 * but color it green/red depending on whether the value is considered “good”.
 * If $goodWhenFalse = true → OFF is green; otherwise ON is green.
 */
function formatStatus(mixed $value, bool $goodWhenFalse = false): string
{
    $b = asBool($value);
    $label = $b ? '[ON]' : '[OFF]';
    $isGood = $goodWhenFalse ? !$b : $b;
    $color = $isGood ? 'green' : 'red';
    return "<fg={$color}>{$label}</>";
}


/**
 * Function to format boolean values as simple symbol
 */
function formatSymbol($value)
{
    return $value ? '<fg=green>[ON]</>' : '<fg=red>[OFF]</>';
}

/**
 * Function to mask sensitive data
 */
function maskSensitive($value, $showLast = 4)
{
    if (empty($value)) {
        return 'Not Set';
    }

    $length = strlen($value);
    if ($length <= $showLast) {
        return str_repeat('*', $length);
    }

    return str_repeat('*', $length - $showLast) . substr($value, -$showLast);
}

/**
 * Function to format file sizes
 */
function formatBytes($size, $precision = 2)
{
    if ($size === 0) return '0 B';
    if ($size === false) return 'N/A';

    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

/**
 * Function to get directory size
 */
function getDirSize($directory)
{
    if (!is_dir($directory)) {
        return false;
    }

    $size = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    try {
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        return false;
    }

    return $size;
}

/**
 * Function to check if a service is running
 */
function isServiceRunning($serviceName)
{
    $output = [];
    $returnCode = 0;

    // Try different methods to check service status
    exec("systemctl is-active $serviceName 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0 && trim($output[0] ?? '') === 'active') {
        return true;
    }

    // Fallback: check if process is running
    exec("pgrep -x $serviceName 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0) {
        return true;
    }

    // Another fallback for MySQL variations
    if ($serviceName === 'mysql') {
        exec("pgrep -x mysqld 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Function to get OS information
 */
function getOSInfo()
{
    $osInfo = [];

    // Try to get OS name and version
    if (file_exists('/etc/os-release')) {
        $osRelease = parse_ini_file('/etc/os-release');
        $osInfo['name'] = $osRelease['PRETTY_NAME'] ?? $osRelease['NAME'] ?? 'Unknown';
        $osInfo['version'] = $osRelease['VERSION'] ?? 'Unknown';
    } elseif (file_exists('/etc/lsb-release')) {
        $lsbRelease = parse_ini_file('/etc/lsb-release');
        $osInfo['name'] = $lsbRelease['DISTRIB_DESCRIPTION'] ?? 'Unknown';
        $osInfo['version'] = $lsbRelease['DISTRIB_RELEASE'] ?? 'Unknown';
    } else {
        $osInfo['name'] = php_uname('s') . ' ' . php_uname('r');
        $osInfo['version'] = php_uname('v');
    }

    return $osInfo;
}

$output = new ConsoleOutput();

echo PHP_EOL;
echo str_pad("InteLIS System Information", 80, "=", STR_PAD_BOTH) . PHP_EOL;
echo PHP_EOL;

// Combined System Overview Table
$isLIS = $general->isLISInstance();
$instanceType = $isLIS ? 'LIS' : 'STS';
$smartConnectURL = $general->getGlobalConfig('vldashboard_url');

$overviewTable = new Table($output);
$overviewTable->setHeaderTitle('OVERVIEW');
$overviewTable->setHeaders(['Property', 'Value', 'Property', 'Value']);

// Get instance info for the overview
$instanceInfo = $db->rawQueryOne("SELECT * FROM s_vlsm_instance LIMIT 1");

$overviewRows = [
    [
        'Instance Type',
        $instanceType,
        'Version',
        defined('VERSION') ? VERSION : 'Unknown',
    ],
    [
        'Current Time',
        formatDateTime(date('Y-m-d H:i:s')),
        'SmartConnect URL',
        $smartConnectURL ?: 'Not Configured',

    ]
];

if ($isLIS) {
    $remoteURL = $general->getRemoteURL();
    $labId = $general->getSystemConfig('sc_testing_lab_id');
    $isConnected = '❌ Not Connected';

    if (!empty($remoteURL)) {
        $isConnected = CommonService::validateStsUrl($remoteURL, $labId) ? '✅ Connected' : '❌ Not Connected';
    }

    $overviewRows[] = new TableSeparator();
    $overviewRows[] = [
        'STS URL',
        "$remoteURL ($isConnected)" ?: 'Not Configured',
        'InteLIS Lab ID',
        $labId ?: 'Not Configured'
    ];
    $overviewRows[] = [
        'STS Token',
        $instanceInfo ? maskSensitive($instanceInfo['sts_token']) : 'Not Set',
        '',
        ''
    ];

    if ($labId) {
        $labDetails = $db->rawQueryOne(
            "SELECT facility_name FROM facility_details WHERE facility_id = ? AND facility_type = 2 AND status = 'active'",
            [$labId]
        );
        $overviewRows[] = [
            'Lab Name',
            $labDetails ? substr($labDetails['facility_name'], 0, 35) : 'Unknown',
            'STS API Key',
            maskSensitive(SYSTEM_CONFIG['sts']['api_key'] ?? '')
        ];
    }
}

$overviewTable->setRows($overviewRows);
$overviewTable->render();
echo PHP_EOL;

// System Diagnostics Table
$diagTable = new Table($output);
$diagTable->setHeaderTitle('SYSTEM DIAGNOSTICS');
$diagTable->setHeaders(['System Info', 'Value', 'Services', 'Status']);

$osInfo = getOSInfo();
$diskTotal = disk_total_space('/');
$diskFree = disk_free_space('/');
$diskUsed = $diskTotal - $diskFree;
$diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 1);

// Color code disk usage if high
$diskUsageDisplay = formatBytes($diskUsed) . " ({$diskUsagePercent}%)";
if ($diskUsagePercent >= 90) {
    $diskUsageDisplay = "<fg=red;options=bold>{$diskUsageDisplay}</>";
} elseif ($diskUsagePercent >= 80) {
    $diskUsageDisplay = "<fg=yellow>{$diskUsageDisplay}</>";
}

$logsSize = getDirSize(ROOT_PATH . "/logs");
$backupsSize = getDirSize(ROOT_PATH . "/backups");

$apacheRunning = isServiceRunning('apache2') || isServiceRunning('httpd');
$mysqlRunning = isServiceRunning('mysql') || isServiceRunning('mysqld') || isServiceRunning('mariadb');
$phpVersion = phpversion();


$diagRows = [
    [
        'OS Name',
        $osInfo['name'],
        'Apache',
        formatStatus($apacheRunning)
    ],
    [
        'PHP Version',
        $phpVersion,
        'MySQL/MariaDB',
        formatStatus($mysqlRunning)
    ],
    [
        'Memory Limit',
        $originalMemoryLimit,
        'Max Exec Time',
        $originalMaxExecutionTime . 's'
    ],
    new TableSeparator(),
    [
        'Disk Total',
        formatBytes($diskTotal),
        'Disk Free',
        formatBytes($diskFree)
    ],
    [
        'Disk Used',
        $diskUsageDisplay,
        'Logs Size',
        formatBytes($logsSize)
    ],
    [
        'Backups Size',
        formatBytes($backupsSize),
        'Root Path',
        ROOT_PATH
    ]
];

$diagTable->setRows($diagRows);
$diagTable->render();
echo PHP_EOL;

// Combined Configuration Table
$configTable = new Table($output);
$configTable->setHeaderTitle('CONFIGURATION');
$configTable->setHeaders(['Database', 'Value', 'System', 'Value']);

$dbConfig = SYSTEM_CONFIG['database'] ?? [];
$systemSettings = SYSTEM_CONFIG['system'] ?? [];
$interfacingEnabled = SYSTEM_CONFIG['interfacing']['enabled'] ?? false;
$adminEmail = SYSTEM_CONFIG['adminEmailUserName'] ?? '';

$configTable->setRows([
    [
        'Host',
        $dbConfig['host'] ?? 'Not Set',
        'Debug Mode',
        // false is good here → invert
        formatStatus((bool)($systemSettings['debug_mode'] ?? false), true)
    ],
    [
        'Port',
        $dbConfig['port'] ?? 'Not Set',
        'Cache DI',
        formatStatus($systemSettings['cache_di'] ?? false)
    ],
    [
        'Database',
        $dbConfig['db'] ?? 'Not Set',
        'Interfacing',
        formatStatus($interfacingEnabled)
    ],
    [
        'Username',
        $dbConfig['username'] ?? 'Not Set',
        'Admin Email',
        substr($adminEmail ?: 'Not Set', 0, 25)
    ],
    [
        'Password',
        maskSensitive($dbConfig['password'] ?? ''),
        'Email Password',
        maskSensitive(SYSTEM_CONFIG['adminEmailPassword'] ?? '')
    ]
]);
$configTable->render();
echo PHP_EOL;

// // Modules in a compact grid
// $moduleTable = new Table($output);
// $moduleTable->setHeaderTitle('MODULE STATUS');
// $moduleTable->setHeaders(['Module', 'Status', 'Module', 'Status']);

// // Get modules from config and use TestsService for names
// $configModules = SYSTEM_CONFIG['modules'] ?? [];
// $modules = [];

// foreach ($configModules as $moduleKey => $enabled) {
//     try {
//         $testName = TestsService::getTestName($moduleKey);
//         $modules[$moduleKey] = $testName;
//     } catch (Exception $e) {
//         $modules[$moduleKey] = ucfirst(str_replace('-', ' ', $moduleKey));
//     }
// }

// $moduleRows = [];
// $moduleKeys = array_keys($modules);
// $moduleNames = array_values($modules);

// for ($i = 0; $i < count($modules); $i += 2) {
//     $row = [];

//     // First module
//     $key1 = $moduleKeys[$i];
//     $name1 = $moduleNames[$i];
//     $status1 = formatSymbol($configModules[$key1] ?? false);
//     $row[] = $name1;
//     $row[] = $status1;

//     // Second module (if exists)
//     if (isset($moduleKeys[$i + 1])) {
//         $key2 = $moduleKeys[$i + 1];
//         $name2 = $moduleNames[$i + 1];
//         $status2 = formatSymbol($configModules[$key2] ?? false);
//         $row[] = $name2;
//         $row[] = $status2;
//     } else {
//         $row[] = '';
//         $row[] = '';
//     }

//     $moduleRows[] = $row;
// }

// $moduleTable->setRows($moduleRows);
// $moduleTable->render();
// echo PHP_EOL;

// Sync Status in compact format (only for LIS instances)
if ($isLIS && $instanceInfo) {
    $syncTable = new Table($output);
    $syncTable->setHeaderTitle('SYNC STATUS');
    $syncTable->setHeaders(['Service', 'Last Sync', 'Service', 'Last Sync']);

    $syncData = [
        'VL -> SmartConnect' => $instanceInfo['vl_last_dash_sync'],
        'Interface Sync' => $instanceInfo['last_interface_sync'],
        'EID -> SmartConnect' => $instanceInfo['eid_last_dash_sync'],
        'Lab Metadata' => $instanceInfo['last_lab_metadata_sync'],
        'COVID -> SmartConnect' => $instanceInfo['covid19_last_dash_sync'],
        'Requests from STS' => $instanceInfo['last_remote_requests_sync'],
        'MetaData -> SmartConnect' => $instanceInfo['last_remote_reference_data_sync'],
        'Results sent to STS' => $instanceInfo['last_remote_results_sync'],

    ];

    $syncRows = [];
    $syncKeys = array_keys($syncData);
    $syncValues = array_values($syncData);

    for ($i = 0; $i < count($syncData); $i += 2) {
        $row = [];

        // First sync item
        $row[] = $syncKeys[$i];
        $row[] = formatDateTime($syncValues[$i]);

        // Second sync item (if exists)
        if (isset($syncKeys[$i + 1])) {
            $row[] = $syncKeys[$i + 1];
            $row[] = formatDateTime($syncValues[$i + 1]);
        } else {
            $row[] = '';
            $row[] = '';
        }

        $syncRows[] = $row;
    }

    $syncTable->setRows($syncRows);
    $syncTable->render();
    echo PHP_EOL;
}

echo str_repeat("=", 80) . PHP_EOL;
