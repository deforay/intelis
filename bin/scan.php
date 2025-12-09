<?php

//bin/scan.php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\TableSeparator;

require_once __DIR__ . "/../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (PHP_SAPI !== 'cli') {
    echo "This script can only be run from command line." . PHP_EOL;
    exit(CLI\ERROR);
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
    if (is_bool($v)) {
        return $v;
    }
    if (is_int($v)) {
        return $v !== 0;
    }
    $s = strtolower(trim((string) $v));
    return !($s === '' || $s === '0' || $s === 'false' || $s === 'off' || $s === 'no');
}

/**
 * Show the REAL value as the label ([ON]/[OFF]),
 * but color it green/red depending on whether the value is considered "good".
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
 * Function to mask sensitive data
 */
function maskSensitive($value, $showLast = 4): string
{
    if (empty($value)) {
        return 'Not Set';
    }

    $length = strlen((string) $value);
    if ($length <= $showLast) {
        return str_repeat('*', $length);
    }

    return str_repeat('*', $length - $showLast) . substr((string) $value, -$showLast);
}

$output = new ConsoleOutput();

// Header with instance type
$headerText = "InteLIS System Information";
echo PHP_EOL;
echo str_pad("", 80, "=") . PHP_EOL;
echo str_pad($headerText, 80, " ", STR_PAD_BOTH) . PHP_EOL;
echo str_pad("", 80, "=") . PHP_EOL;
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
    $connectionStatus = '<fg=red>✗ Not Connected</>';

    if (!empty($remoteURL)) {
        $connectionStatus = CommonService::validateStsUrl($remoteURL, $labId)
            ? '<fg=green>✓ Connected</>'
            : '<fg=red>✗ Not Connected</>';
    }

    $overviewRows[] = new TableSeparator();
    $overviewRows[] = [
        'STS URL',
        $remoteURL ?: '<fg=yellow>Not Configured</>',
        'Connection',
        $connectionStatus
    ];
    $overviewRows[] = [
        'InteLIS Lab ID',
        $labId ?: '<fg=yellow>Not Configured</>',
        'STS Token',
        $instanceInfo ? maskSensitive($instanceInfo['sts_token']) : '<fg=yellow>Not Set</>'
    ];

    if ($labId) {
        $labDetails = $db->rawQueryOne(
            "SELECT facility_name FROM facility_details WHERE facility_id = ? AND facility_type = 2 AND status = 'active'",
            [$labId]
        );
        $overviewRows[] = [
            'Lab Name',
            $labDetails ? substr((string) $labDetails['facility_name'], 0, 40) : '<fg=yellow>Unknown</>',
            'STS API Key',
            maskSensitive(SYSTEM_CONFIG['sts']['api_key'] ?? '')
        ];
    }
}

$overviewTable->setRows($overviewRows);
$overviewTable->render();
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
        formatStatus((bool) ($systemSettings['debug_mode'] ?? false), true)
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
        substr((string) ($adminEmail ?: 'Not Set'), 0, 25)
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
    $counter = count($syncData);

    for ($i = 0; $i < $counter; $i += 2) {
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

// Run health.php for system health checks
$healthScript = __DIR__ . '/health.php';
if (file_exists($healthScript)) {
    $process = new Process([PHP_BINARY, $healthScript]);
    $process->setTty(Process::isTtySupported());
    $process->run();
    echo $process->getOutput();
}
