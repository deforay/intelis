<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . '/../bootstrap.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$systemConfig = $general->getSystemConfig();

if ($systemConfig['sc_user_type'] == 'remoteuser') {
    exit("Script not required for STS instance" . PHP_EOL);
}

$scriptName = basename(__FILE__);

// Check for force flag (-f or --force)
$forceRun = in_array('-f', $argv) || in_array('--force', $argv);

if (!$forceRun) {
    // Check if the script has already been run
    $db->where('script_name', $scriptName);
    $executed = $db->getOne('s_run_once_scripts_log');

    if ($executed) {
        // Script has already been run
        exit("Script $scriptName has already been executed. Exiting...");
    }
}

/* Save Province / State details to geolocation table */
$query = "SELECT * FROM instruments";
$instrumentResult = $db->rawQuery($query);

foreach ($instrumentResult as $row) {

    if (is_int($row['instrument_id'])) {
        $instrumentId = $general->generateUUID();
        $db->where("instrument_id", $row['instrument_id']);
        $db->update('instruments', array('instrument_id' => $instrumentId));
    } else {
        $instrumentId = $row['instrument_id'];
    }

    $db->where("vl_test_platform", $row["machine_name"]);
    $db->update('form_vl', array('instrument_id' => $instrumentId));

    $db->where("eid_test_platform", $row["machine_name"]);
    $db->update('form_eid', array('instrument_id' => $instrumentId));

    $db->where("testing_platform", $row["machine_name"]);
    $db->update('covid19_tests', array('instrument_id' => $instrumentId));

    $db->where("hepatitis_test_platform", $row["machine_name"]);
    $db->update('form_hepatitis', array('instrument_id' => $instrumentId));

    $db->where("tb_test_platform", $row["machine_name"]);
    $db->update('form_tb', array('instrument_id' => $instrumentId));
}

// After successful execution, log the script run
$data = [
    'script_name' => $scriptName,
    'execution_date' => DateUtility::getCurrentDateTime(),
    'status' => 'executed'
];

$db->insert('s_run_once_scripts_log', $data);

echo "$scriptName executed and logged successfully" . PHP_EOL;
