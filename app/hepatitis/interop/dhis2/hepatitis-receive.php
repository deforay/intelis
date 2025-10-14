<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../../../bootstrap.php");
}
$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
    $interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}

if (empty($interopConfig)) {
    echo "Interop config not found";
    exit(0);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

// let us do init first
require_once(APPLICATION_PATH . '/hepatitis/interop/dhis2/hepatitis-init.php');

$instanceId = $general->getInstanceId();

$fileArray = array(
    COUNTRY\RWANDA => 'forms/receive-rwanda.php'
);


require_once($fileArray[$arr['vl_form']]);
