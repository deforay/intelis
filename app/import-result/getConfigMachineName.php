<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$importMachineTable = "instrument_machines";
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$configId = base64_decode((string) $_POST['configId']);
$iQuery = "SELECT instrument_id,machine_name,import_machine_file_name FROM instruments where import_machine_file_name='$configId'";
$iResult = $db->rawQuery($iQuery);

$configMachineQuery = "SELECT config_machine_id,config_machine_name,file_name,date_format  from instrument_machines where instrument_id=" . $iResult[0]['instrument_id'];
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
    $configMachineData = array('instrument_id' => $iResult[0]['instrument_id'], 'config_machine_name' => $iResult[0]['machine_name'] . " 1");
    $db->insert($importMachineTable, $configMachineData);
    $configMachineInfo = $db->query($configMachineQuery);
    $configMachine .= '<option value"">-- Select --</option>';
    foreach ($configMachineInfo as $machine) {
        $configMachine .= '<option value="' . $machine['config_machine_id'] . '" data-filename="' . $machine['file_name'] . '" data-dateformat="' . $machine['date_format'] . '" selected="selected">' . ($machine['config_machine_name']) . '</option>';
    }
}
echo $configMachine;
