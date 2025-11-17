<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$db = ContainerRegistry::get(DatabaseService::class);

$importMachineTable = "instrument_machines";
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$configId = base64_decode((string) $_POST['configId']);
$cacheKey = "instrument_config_$configId";

$output = _getFromFileCache($cacheKey, function () use ($db, $importMachineTable, $configId): string {

    $iResult = $db->rawQueryOne(
        "SELECT instrument_id, machine_name, import_machine_file_name FROM instruments WHERE instrument_id = ?",
        [$configId]
    );

    $configMachineInfo = $db->rawQuery(
        "SELECT `config_machine_id`, `config_machine_name`, `file_name`, `date_format` FROM instrument_machines WHERE instrument_id = ?",
        [$iResult['instrument_id']]
    );

    if (empty($configMachineInfo)) {
        $deviceName = $iResult['machine_name'] . "- 1";
        $db->insert($importMachineTable, [
            'instrument_id' => $iResult['instrument_id'],
            'file_name' => $iResult['import_machine_file_name'],
            'config_machine_name' => $deviceName
        ]);
        $deviceId = $db->getInsertId();

        $configMachineInfo = [[
            'config_machine_id' => $deviceId,
            'config_machine_name' => $deviceName,
            'file_name' => $iResult['import_machine_file_name'],
            'date_format' => ''
        ]];
    }

    $options = '<option value="">' . _translate('-- Select --', true) . '</option>';
    $selectedAttr = count($configMachineInfo) == 1 ? ' selected' : '';

    foreach ($configMachineInfo as $machine) {
        $fileName = ($machine['file_name'] ?: $iResult['import_machine_file_name']) ?: '';
        $options .= sprintf(
            '<option value="%s" data-filename="%s" data-dateformat="%s"%s>%s</option>',
            $machine['config_machine_id'],
            htmlspecialchars((string) $fileName),
            htmlspecialchars((string) $machine['date_format']),
            $selectedAttr,
            htmlspecialchars((string) $machine['config_machine_name'])
        );
    }

    return $options;
}, ['instruments']);

echo $output;
