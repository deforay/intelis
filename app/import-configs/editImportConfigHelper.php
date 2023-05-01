<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var MysqliDb $db */
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "instruments";
$importMachineTable = "instrument_machines";
$importControlTable = "instrument_controls";

$configId = (int) base64_decode($_POST['configId']);

$configControlQuery = "SELECT * FROM instrument_controls WHERE config_id=$configId";
$configControlInfo = $db->query($configControlQuery);
// echo "<pre>";print_r($_POST);die;
try {
    if (trim($_POST['configurationName']) != "") {

        if (isset($_POST['supportedTests']) && sizeof($_POST['supportedTests']) > 0) {
            foreach ($_POST['supportedTests'] as $test) {
                $configDir = __DIR__;
                if (!file_exists($configDir)) {
                    mkdir($configDir, 0777, true);
                }
                $configFile = $configDir . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
                if (!file_exists($configFile)) {
                    $fp = fopen($configFile, 'w');
                    fwrite($fp, '');
                    fclose($fp);
                }
            }
        }

        $matchedTests = array_diff($_POST['userTestType'], $_POST['supportedTests']);
        foreach ($matchedTests as $key => $row) {
            $_POST['reviewedBy'][$key] = "";
            $_POST['approvedBy'][$key] = "";
        }
        $_POST['reviewedBy'] = !empty($_POST['reviewedBy']) ? json_encode(array_combine($_POST['userTestType'], $_POST['reviewedBy'])) : null;
        $_POST['approvedBy'] = !empty($_POST['approvedBy']) ? json_encode(array_combine($_POST['userTestType'], $_POST['approvedBy'])) : null;

        $_POST['supportedTests'] = !empty($_POST['supportedTests']) ? json_encode($_POST['supportedTests']) : null;

        $importConfigData = array(
            'machine_name' => $_POST['configurationName'],
            'lab_id' => $_POST['testingLab'],
            'supported_tests' => $_POST['supportedTests'],
            'import_machine_file_name' => $_POST['configurationFile'],
            'lower_limit' => $_POST['lowerLimit'],
            'higher_limit' => $_POST['higherLimit'],
            'max_no_of_samples_in_a_batch' => $_POST['maxNOfSamplesInBatch'],
            'low_vl_result_text' => $_POST['lowVlResultText'],
            'reviewed_by' => !empty($_POST['reviewedBy']) ? $_POST['reviewedBy'] : null,
            'approved_by' => !empty($_POST['approvedBy']) ? $_POST['approvedBy'] : null,
            'status' => $_POST['status']
        );
        $db = $db->where('config_id', $configId);
        //print_r($vldata);die;
        $db->update($tableName, $importConfigData);
        if (count($_POST['configMachineName']) > 0) {
            for ($c = 0; $c < count($_POST['configMachineName']); $c++) {
                if (trim($_POST['configMachineName'][$c]) != '') {
                    $pocDev = 'no';
                    if (trim($_POST['latitude'][$c]) != '' && trim($_POST['longitude'][$c]) != '') {
                        $pocDev = 'yes';
                    }
                    if (isset($_POST['configMachineId'][$c]) && $_POST['configMachineId'][$c] != '') {
                        $configMachineData = array('config_machine_name' => $_POST['configMachineName'][$c], 'date_format' => !empty($_POST['dateFormat'][$c]) ? $_POST['dateFormat'][$c] : null, 'file_name' => !empty($_POST['fileName'][$c]) ? $_POST['fileName'][$c] : null, 'poc_device' => $pocDev, 'latitude' => $_POST['latitude'][$c], 'longitude' => $_POST['longitude'][$c], 'updated_datetime' => DateUtility::getCurrentDateTime());
                        $db = $db->where('config_machine_id', $_POST['configMachineId'][$c]);
                        $db->update($importMachineTable, $configMachineData);
                    } else {
                        $configMachineData = array('config_id' => $configId, 'config_machine_name' => $_POST['configMachineName'][$c], 'date_format' => !empty($_POST['dateFormat'][$c]) ? $_POST['dateFormat'][$c] : null, 'file_name' => !empty($_POST['fileName'][$c]) ? $_POST['fileName'][$c] : null, 'poc_device' => $pocDev, 'latitude' => $_POST['latitude'][$c], 'longitude' => $_POST['longitude'][$c], 'updated_datetime' => DateUtility::getCurrentDateTime());
                        $db->insert($importMachineTable, $configMachineData);
                    }
                }
            }
        }

        if ($configId > 0 && isset($_POST['testType']) && count($_POST['testType']) > 0) {
            if (count($configControlInfo) > 0) {
                foreach ($_POST['testType'] as $key => $val) {
                    $cQuery = "SELECT * FROM instrument_controls WHERE config_id= " . $configId . " AND test_type like '" . $val . "'";
                    $cResult = $db->rawQueryOne($cQuery);
                    if (trim($val) != '' && $cResult) {
                        $configControlData = array('number_of_in_house_controls' => $_POST['noHouseCtrl'][$key], 'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key], 'number_of_calibrators' => $_POST['noCalibrators'][$key]);
                        $db = $db->where('config_id', $configId);
                        $db = $db->where('test_type', $val);
                        $db->update($importControlTable, $configControlData);
                    } else {
                        $configControlData = array('test_type' => $val, 'config_id' => $configId, 'number_of_in_house_controls' => $_POST['noHouseCtrl'][$key], 'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key], 'number_of_calibrators' => $_POST['noCalibrators'][$key]);
                        $db->insert($importControlTable, $configControlData);
                    }
                }
            } else {
                foreach ($_POST['testType'] as $key => $val) {
                    if (trim($val) != '') {
                        $configControlData = array('test_type' => $val, 'config_id' => $configId, 'number_of_in_house_controls' => $_POST['noHouseCtrl'][$key], 'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key], 'number_of_calibrators' => $_POST['noCalibrators'][$key]);
                        $db->insert($importControlTable, $configControlData);
                    }
                }
            }
        }
        $_SESSION['alertMsg'] = _("Import config details updated successfully");
        $configDir = __DIR__;
        $configFileVL = $configDir . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileEID = $configDir . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileCovid19 = $configDir . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileHepatitis = $configDir . DIRECTORY_SEPARATOR . "hepatitis" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];


        if (!file_exists($configDir)) {
            mkdir($configDir, 0777, true);
        }

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
            fwrite($fp, '');
            fclose($fp);
        }

        if (!file_exists($configFileHepatitis)) {
            $fp = fopen($configFileHepatitis, 'w');
            fwrite($fp, '');
            fclose($fp);
        }
    }
    header("Location:importConfig.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
