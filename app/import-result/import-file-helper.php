<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$machineImportScript = $_POST['fileName'];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

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
    throw new SystemException(_translate('Invalid Test Type'));
}



if (file_exists($machineImportScript) && !is_dir($machineImportScript)) {
    MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results");
    require_once($machineImportScript);
} else {
    throw new SystemException(_translate("Import Script not found"));
}
