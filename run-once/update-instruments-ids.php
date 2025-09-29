<?php

use App\Exceptions\SystemException;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
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
$scriptName = basename(__FILE__);

// Check for force flag (-f or --force)
$forceRun = in_array('-f', $argv) || in_array('--force', $argv);

if (!$forceRun) {
    // Check if the script has already been run
    $db->where('script_name', $scriptName);
    $executed = $db->getOne('s_run_once_scripts_log');

    if ($executed) {
        // Script has already been run
        echo("Script $scriptName has already been executed. Exiting...");
        exit(0);
    }
}

try {

    if ($general->isSTSInstance()) {
        $scriptSucceeded = true;
        throw new SystemException("Script $scriptName not required for STS instance" . PHP_EOL);
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

    $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', $data);

    echo "$scriptName executed and logged successfully" . PHP_EOL;
} catch (Throwable $e) {
    LoggerUtility::logError('Manifest hash update script failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
} finally {
    if ($scriptSucceeded || $forceRun) {
        $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', [
            'script_name' => $scriptName,
            'execution_date' => DateUtility::getCurrentDateTime(),
            'status' => $scriptSucceeded ? 'executed' : 'forced'
        ]);
    }
}

if (!$scriptSucceeded) {
    exit(1);
}
