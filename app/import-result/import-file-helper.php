<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$machineImportScript = ($_POST['fileName']);

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

$type = $_POST['type'];

$directoryMap = [
    'vl' => 'vl',
    'eid' => 'eid',
    'covid19' => 'covid-19',
    'hepatitis' => 'hepatitis',
    'tb' => 'tb',
];

if (isset($directoryMap[$type])) {
    $directoryName = $directoryMap[$type];
    $machineImportScript = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . "instruments") . DIRECTORY_SEPARATOR . $directoryName . DIRECTORY_SEPARATOR . $machineImportScript;
} else {
    echo "Invalid type";
    exit();
}

if (file_exists($machineImportScript)) {
    require_once($machineImportScript);
} else {
    echo "Import Script not found";
    exit();
}
