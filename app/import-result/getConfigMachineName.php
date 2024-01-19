<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$importMachineTable = "instrument_machines";
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$configId = base64_decode($_POST['configId']);
$iQuery = "SELECT instrument_id,
                machine_name
                import_machine_file_name
            FROM instruments
            WHERE instrument_id= ?";
$iResult = $db->rawQueryOne($iQuery, [$configId]);

$configMachineQuery = "SELECT config_machine_id,
                                config_machine_name,
                                file_name,
                                `date_format`
                        FROM instrument_machines
                        WHERE instrument_id= ?";
$configMachineInfo = $db->rawQuery($configMachineQuery, [$iResult['instrument_id']]);
$configMachine = '<option value"">' . _translate('-- Select --', true) . '</option>';
if (!empty($configMachineInfo)) {

    $selected = count($configMachineInfo) == 1 ? "selected" : '';

    foreach ($configMachineInfo as $machine) {
        $fileName = $machine['file_name'] ?? $iResult['import_machine_file_name'] ?? '';
        $configMachine .= '<option value="' . $machine['config_machine_id'] . '" data-filename="' . $fileName . '" data-dateformat="' . $machine['date_format'] . '" selected=' . $selected . '>' . ($machine['config_machine_name']) . '</option>';
    }
} else {
    $configMachineData = array('instrument_id' => $iResult['instrument_id'], 'config_machine_name' => $iResult['machine_name'] . " 1");
    $db->insert($importMachineTable, $configMachineData);
    //$configMachineInfo = $db->rawQuery($configMachineQuery, [$iResult['instrument_id']]);

    foreach ($configMachineInfo as $machine) {
        $fileName = $machine['file_name'] ?? $iResult['import_machine_file_name'] ?? '';
        $configMachine .= '<option value="' . $machine['config_machine_id'] . '" data-filename="' . $fileName . '" data-dateformat="' . $machine['date_format'] . '" selected="selected">' . ($machine['config_machine_name']) . '</option>';
    }
}
echo $configMachine;
