<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

$tableName = "instruments";
$importMachineTable = "instrument_machines";

$configId = $_POST['instrumentId'];
$testType = $_POST['testType'];
$machineId = $_POST['machine'];

$configQuery = "SELECT config_id FROM $tableName WHERE machine_name LIKE ?";
$configInfo = $db->rawQueryOne($configQuery, [$configId]);

$options = '<option value="">--Select--</option>';
if (isset($configInfo) && $configInfo != "") {
    $configMachineQuery = "SELECT * FROM $importMachineTable WHERE config_id LIKE ?";
    $configMachineInfo = $db->rawQuery($configMachineQuery, [$configInfo['config_id']]);
    foreach ($configMachineInfo as $machine) {
        $selected = (isset($machineId) && $machine['config_machine_id'] == $machineId) ? "selected='selected'" : "";
        $options .= '<option value="' . $machine['config_machine_id'] . '" ' . $selected . '>' . ($machine['config_machine_name']) . '</option>';
    }
}
echo $options;
