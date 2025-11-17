<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "instruments";
$importMachineTable = "instrument_machines";
$importControlTable = "instrument_controls";

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$configId = base64_decode((string) $_POST['configId']);


$configControlQuery = "SELECT * FROM instrument_controls WHERE instrument_id=?";
$configControlInfo = $db->rawQuery($configControlQuery, [$configId]);

try {
    if (trim((string) $_POST['configurationName']) !== "") {
        if (!empty($_POST['supportedTests'])) {
            foreach ($_POST['supportedTests'] as $test) {
                $configDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . "instruments";
                MiscUtility::makeDirectory($configDir . DIRECTORY_SEPARATOR . $test);
                $configFile = $configDir . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
                if (!file_exists($configFile)) {
                    $fp = fopen($configFile, 'w');
                    if ($fp !== false && !empty($fp)) {
                        fwrite($fp, '');
                        fclose($fp);
                    }
                }
            }
        }

        $matchedTests = array_diff($_POST['userTestType'] ?? [], $_POST['supportedTests'] ?? []);
        foreach (array_keys($matchedTests) as $key) {
            $_POST['reviewedBy'][$key] = "";
            $_POST['approvedBy'][$key] = "";
        }
        $_POST['reviewedBy'] = empty($_POST['reviewedBy']) ? null : json_encode(array_combine($_POST['userTestType'], $_POST['reviewedBy']));
        $_POST['approvedBy'] = empty($_POST['approvedBy']) ? null : json_encode(array_combine($_POST['userTestType'], $_POST['approvedBy']));

        $_POST['supportedTests'] = empty($_POST['supportedTests']) ? null : json_encode($_POST['supportedTests']);

        $importConfigData = [
            'machine_name' => $_POST['configurationName'],
            'lab_id' => $_POST['testingLab'],
            'supported_tests' => $_POST['supportedTests'] ?? null,
            'import_machine_file_name' => $_POST['configurationFile'] ?? null,
            'lower_limit' => $_POST['lowerLimit'] ?? 0,
            'higher_limit' => $_POST['higherLimit'] ?? null,
            'max_no_of_samples_in_a_batch' => $_POST['maxNOfSamplesInBatch'] ?? null,
            'low_vl_result_text' => $_POST['lowVlResultText'] ?? null,
            'additional_text' => $_POST['additionalText'] ?? null,
            'reviewed_by' => empty($_POST['reviewedBy']) ? null : $_POST['reviewedBy'],
            'approved_by' => empty($_POST['approvedBy']) ? null : $_POST['approvedBy'],
            'status' => $_POST['status']
        ];
        $db->where('instrument_id', $configId);

        $db->update($tableName, $importConfigData);
        if (!empty($_POST['configMachineName'])) {
            $counter = count($_POST['configMachineName']);
            for ($c = 0; $c < $counter; $c++) {
                if (trim((string) $_POST['configMachineName'][$c]) !== '') {
                    $pocDev = 'no';
                    if (trim((string) $_POST['latitude'][$c]) !== '' && trim((string) $_POST['longitude'][$c]) !== '') {
                        $pocDev = 'yes';
                    }
                    if (isset($_POST['configMachineId'][$c]) && $_POST['configMachineId'][$c] != '') {
                        $configMachineData = ['config_machine_name' => $_POST['configMachineName'][$c], 'date_format' => empty($_POST['dateFormat'][$c]) ? null : $_POST['dateFormat'][$c], 'file_name' => empty($_POST['fileName'][$c]) ? null : $_POST['fileName'][$c], 'poc_device' => $pocDev, 'latitude' => $_POST['latitude'][$c], 'longitude' => $_POST['longitude'][$c], 'updated_datetime' => DateUtility::getCurrentDateTime()];
                        $db->where('config_machine_id', $_POST['configMachineId'][$c]);
                        $db->update($importMachineTable, $configMachineData);
                    } else {
                        $configMachineData = ['instrument_id' => $configId, 'config_machine_name' => $_POST['configMachineName'][$c], 'date_format' => empty($_POST['dateFormat'][$c]) ? null : $_POST['dateFormat'][$c], 'file_name' => empty($_POST['fileName'][$c]) ? null : $_POST['fileName'][$c], 'poc_device' => $pocDev, 'latitude' => $_POST['latitude'][$c], 'longitude' => $_POST['longitude'][$c], 'updated_datetime' => DateUtility::getCurrentDateTime()];
                        $db->insert($importMachineTable, $configMachineData);
                    }
                }
            }
        }

        if (!empty($configId) && !empty($_POST['testType'])) {

            $db->where('instrument_id', $configId);
            $db->delete($importControlTable);

            foreach ($_POST['testType'] as $key => $val) {
                if (trim((string) $val) !== '') {
                    $configControlData = [
                        'test_type' => $val,
                        'instrument_id' => $configId,
                        'number_of_in_house_controls' => $_POST['noHouseCtrl'][$key],
                        'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key],
                        'number_of_calibrators' => $_POST['noCalibrators'][$key]
                    ];
                    $db->insert($importControlTable, $configControlData);
                }
            }
        }
        $_SESSION['alertMsg'] = _translate("Instrument updated successfully");
        $configDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . "instruments";
        $configFileVL = $configDir . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileEID = $configDir . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileCovid19 = $configDir . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileHepatitis = $configDir . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileCD4 = $configDir . DIRECTORY_SEPARATOR . "cd4" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];



        MiscUtility::makeDirectory($configDir . DIRECTORY_SEPARATOR . $test);

        if (!file_exists($configFileVL)) {
            $fp = fopen($configFileVL, 'w');
            fwrite($fp, '');
            fclose($fp);
        }

        if (!file_exists($configFileEID)) {
            $fp = fopen($configFileEID, 'w');
            fwrite($fp, '');
            fclose($fp);
        }

        if (!file_exists($configFileCovid19)) {
            $fp = fopen($configFileCovid19, 'w');
            if ($fp !== false && !empty($fp)) {
                fwrite($fp, '');
                fclose($fp);
            }
        }

        if (!file_exists($configFileHepatitis)) {
            $fp = fopen($configFileHepatitis, 'w');
            fwrite($fp, '');
            fclose($fp);
        }

        if (!file_exists($configFileCD4)) {
            $fp = fopen($configFileCD4, 'w');
            fwrite($fp, '');
            fclose($fp);
        }
    }
} catch (Exception $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
_invalidateFileCacheByTags(['instruments']);
header("Location:/instruments/instruments.php");
