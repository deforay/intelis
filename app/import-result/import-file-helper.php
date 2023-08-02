<?php

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

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
    throw new SystemException(_('Invalid Test Type'));
}



if (file_exists($machineImportScript)) {
    MiscUtility::makeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results");
    require_once($machineImportScript);
} else {
    throw new SystemException(_("Import Script not found"));
}
