<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../../../bootstrap.php");
}
require_once(APPLICATION_PATH . '/../configs/config.interop.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

// let us do init first
require_once(APPLICATION_PATH . '/hepatitis/interop/dhis2/hepatitis-init.php');

$instanceId = $general->getInstanceId();

$fileArray = array(
    7 => 'forms/receive-rwanda.php'
);


require($fileArray[$arr['vl_form']]);
