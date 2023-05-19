<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$importMachineTable = "instrument_machines";
// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

$configId = base64_decode($_POST['configId']);
$iQuery = "SELECT config_id,machine_name,import_machine_file_name FROM instruments where import_machine_file_name='$configId'";
$iResult = $db->rawQuery($iQuery);

$configMachineQuery = "SELECT config_machine_id,config_machine_name,file_name,date_format  from instrument_machines where config_id=" . $iResult[0]['config_id'];
$configMachineInfo = $db->query($configMachineQuery);
$configMachine = '';
if (!empty($configMachineInfo)) {
    $configMachine .= '<option value"">-- Select --</option>';
    $selected = '';
    if (count($configMachineInfo) == 1) {
        $selected = "selected";
    }
    foreach ($configMachineInfo as $machine) {
        $configMachine .= '<option value="' . $machine['config_machine_id'] . '" data-filename="' . $machine['file_name'] . '" data-dateformat="' . $machine['date_format'] . '" selected=' . $selected . '>' . ($machine['config_machine_name']) . '</option>';
    }
} else {
    $configMachineData = array('config_id' => $iResult[0]['config_id'], 'config_machine_name' => $iResult[0]['machine_name'] . " 1");
    $db->insert($importMachineTable, $configMachineData);
    $configMachineInfo = $db->query($configMachineQuery);
    $configMachine .= '<option value"">-- Select --</option>';
    foreach ($configMachineInfo as $machine) {
        $configMachine .= '<option value="' . $machine['config_machine_id'] . '" data-filename="' . $machine['file_name'] . '" data-dateformat="' . $machine['date_format'] . '" selected="selected">' . ($machine['config_machine_name']) . '</option>';
    }
}
echo $configMachine;
