<?php

use Psr\Http\Message\ServerRequestInterface;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$_POST['configurationName'] = trim((string) $_POST['configurationName']);
try {
    if (isset($_POST['configurationName']) && ($_POST['configurationName'] !== '' && $_POST['configurationName'] !== '0')) {

        if (!empty($_POST['supportedTests'])) {
            foreach ($_POST['supportedTests'] as $test) {
                $configDir = realpath(__DIR__);
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
        $matchedTests = array_diff($_POST['userTestType'], $_POST['supportedTests']);
        foreach (array_keys($matchedTests) as $key) {
            $_POST['reviewedBy'][$key] = "";
            $_POST['approvedBy'][$key] = "";
        }
        $_POST['reviewedBy'] = empty($_POST['reviewedBy']) ? null : json_encode(array_combine($_POST['userTestType'], $_POST['reviewedBy']));
        $_POST['approvedBy'] = empty($_POST['approvedBy']) ? null : json_encode(array_combine($_POST['userTestType'], $_POST['approvedBy']));

        $_POST['supportedTests'] = empty($_POST['supportedTests']) ? null : json_encode($_POST['supportedTests']);

        $data = [
            'instrument_id' => MiscUtility::generateULID(),
            'machine_name' => $_POST['configurationName'],
            'lab_id' => $_POST['testingLab'],
            'supported_tests' => $_POST['supportedTests'] ?? null,
            'import_machine_file_name' => $_POST['configurationFile'],
            'lower_limit' => $_POST['lowerLimit'] ?? 0,
            'higher_limit' => $_POST['higherLimit'] ?? null,
            'max_no_of_samples_in_a_batch' => $_POST['maxNOfSamplesInBatch'] ?? null,
            'low_vl_result_text' => $_POST['lowVlResultText'] ?? null,
            'additional_text' => $_POST['additionalText'] ?? null,
            'reviewed_by' => $_POST['reviewedBy'] ?? null,
            'approved_by' => $_POST['approvedBy'] ?? null,
            'status' => 'active'
        ];
        $id = $db->insert($tableName, $data);
        if ($id !== false && !empty($_POST['configMachineName'])) {
            $counter = count($_POST['configMachineName']);
            for ($c = 0; $c < $counter; $c++) {
                $pocDev = 'no';
                if (trim((string) $_POST['latitude'][$c]) !== '' && trim((string) $_POST['longitude'][$c]) !== '') {
                    $pocDev = 'yes';
                }
                if (trim((string) $_POST['configMachineName'][$c]) !== '') {
                    $configMachineData = [
                        'instrument_id' => $data['instrument_id'],
                        'config_machine_name' => $_POST['configMachineName'][$c],
                        'date_format' => empty($_POST['dateFormat'][$c]) ? null : $_POST['dateFormat'][$c],
                        'file_name' => empty($_POST['fileName'][$c]) ? null : $_POST['fileName'][$c],
                        'poc_device' => $pocDev,
                        'latitude' => $_POST['latitude'][$c],
                        'longitude' => $_POST['longitude'][$c],
                        'updated_datetime' => DateUtility::getCurrentDateTime()
                    ];
                    $db->insert($importMachineTable, $configMachineData);
                }
            }
        }

        if ($id !== false && !empty($_POST['testType'])) {
            foreach ($_POST['testType'] as $key => $val) {
                if (trim((string) $val) !== '') {
                    $configControlData = ['test_type' => $val, 'instrument_id' => $data['instrument_id'], 'number_of_in_house_controls' => $_POST['noHouseCtrl'][$key], 'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key], 'number_of_calibrators' => $_POST['noCalibrators'][$key]];
                    $db->insert($importControlTable, $configControlData);
                }
            }
        }

        $_SESSION['alertMsg'] = _translate("Instrument import configuration initited for ") . $_POST['configurationName'] . _translate("Please proceed to write the import logic in the file ") . $_POST['configurationFile'] . _translate(" present in instruments folder");
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
