<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../startup.php');  

$general = new \Vlsm\Models\General();
$tableName = "import_config";
$importMachineTable = "import_config_machines";
$importControlTable = "import_config_controls";
// print_r($_POST);die;
$_POST['configurationName'] = trim($_POST['configurationName']);
try {
    if (!empty($_POST['configurationName'])) {

        $_POST['supportedTests'] = !empty($_POST['supportedTests']) ? json_encode($_POST['supportedTests']) : null;

        $data = array(
            'machine_name' => $_POST['configurationName'],
            'supported_tests' => $_POST['supportedTests'],
            'import_machine_file_name' => $_POST['configurationFile'],
            'lower_limit' => $_POST['lowerLimit'],
            'higher_limit' => $_POST['higherLimit'],
            'max_no_of_samples_in_a_batch' => $_POST['maxNOfSamplesInBatch'],
            'low_vl_result_text' => $_POST['lowVlResultText'],
            'status' => 'active'
        );
        //print_r($data);die;
        $id = $db->insert($tableName, $data);
        if ($id > 0 && count($_POST['configMachineName']) > 0) {
            for ($c = 0; $c < count($_POST['configMachineName']); $c++) {
                $pocDev = 'no';
                if(trim($_POST['latitude'][$c]) != '' && trim($_POST['longitude'][$c]) != ''){
                    $pocDev = 'yes';
                }
                if (trim($_POST['configMachineName'][$c]) != '') {
                    $configMachineData = array('config_id' => $id, 'config_machine_name' => $_POST['configMachineName'][$c], 'poc_device' => $pocDev, 'latitude' => $_POST['latitude'][$c], 'longitude' => $_POST['longitude'][$c], 'updated_datetime'=> $general->getDateTime());
                    $db->insert($importMachineTable, $configMachineData);
                }
            }
        }
        if ($id > 0 && isset($_POST['testType']) && count($_POST['testType']) > 0) {
            foreach ($_POST['testType'] as $key => $val) {
                if (trim($val) != '') {
                    $configControlData = array('test_type' => $val, 'config_id' => $id, 'number_of_in_house_controls' => $_POST['noHouseCtrl'][$key], 'number_of_manufacturer_controls' => $_POST['noManufacturerCtrl'][$key], 'number_of_calibrators' => $_POST['noCalibrators'][$key]);
                    $db->insert($importControlTable, $configControlData);
                }
            }
        }
        $_SESSION['alertMsg'] = "Result Import configuration initited for " . $_POST['configurationName'] . ". Please proceed to write the import logic in the file " . $_POST['configurationFile'] . " present in import-configs folder";

        $configDir = __DIR__;
        $configFileVL = $configDir . DIRECTORY_SEPARATOR . "vl" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileEID = $configDir . DIRECTORY_SEPARATOR . "eid" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];
        $configFileCovid19 = $configDir . DIRECTORY_SEPARATOR . "covid-19" . DIRECTORY_SEPARATOR . $_POST['configurationFile'];


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
    }
    header("location:importConfig.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
