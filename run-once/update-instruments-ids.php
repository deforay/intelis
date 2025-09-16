<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;

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

if ($general->isSTSInstance()) {
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

$updatedOn = DateUtility::getCurrentDateTime();

foreach ($instrumentResult as $row) {

    $oldInstrumentId = null;
    if (is_numeric($row['instrument_id'])) {
        $oldInstrumentId = $row['instrument_id'];
        $instrumentId = MiscUtility::generateULID();
        $db->where("instrument_id", $row['instrument_id']);
        $db->update('instruments', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);

        $db->where("instrument_id", $row['instrument_id']);
        $db->update('instrument_controls', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);

        $db->where("instrument_id", $row['instrument_id']);
        $db->update('instrument_machines', ['instrument_id' => $instrumentId, 'updated_datetime' => $updatedOn]);
    } else {
        $instrumentId = $row['instrument_id'];
    }

    $db->where("vl_test_platform", $row["machine_name"]);
    if (!empty($oldInstrumentId)) {
        $db->orWhere("instrument_id", $oldInstrumentId);
    }
    $db->update('form_vl', ['instrument_id' => $instrumentId]);

    $db->where("eid_test_platform", $row["machine_name"]);
    if (!empty($oldInstrumentId)) {
        $db->orWhere("instrument_id", $oldInstrumentId);
    }
    $db->update('form_eid', ['instrument_id' => $instrumentId]);

    $db->where("testing_platform", $row["machine_name"]);
    if (!empty($oldInstrumentId)) {
        $db->orWhere("instrument_id", $oldInstrumentId);
    }
    $db->update('covid19_tests', ['instrument_id' => $instrumentId]);

    $db->where("hepatitis_test_platform", $row["machine_name"]);
    if (!empty($oldInstrumentId)) {
        $db->orWhere("instrument_id", $oldInstrumentId);
    }
    $db->update('form_hepatitis', ['instrument_id' => $instrumentId]);

    $db->where("tb_test_platform", $row["machine_name"]);
    if (!empty($oldInstrumentId)) {
        $db->orWhere("instrument_id", $oldInstrumentId);
    }
    $db->update('form_tb', ['instrument_id' => $instrumentId]);
}

// After successful execution, log the script run
$data = [
    'script_name' => $scriptName,
    'execution_date' => DateUtility::getCurrentDateTime(),
    'status' => 'executed'
];

$db->setQueryOption ('IGNORE')->insert('s_run_once_scripts_log', $data);

echo "$scriptName executed and logged successfully" . PHP_EOL;
