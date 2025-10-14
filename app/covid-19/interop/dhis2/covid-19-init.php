<?php


use App\Interop\Dhis2;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
    $interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}

if (empty($interopConfig)) {
    echo "Interop config not found";
    exit(0);
}

$dhis2 = new Dhis2(
    $interopConfig['DHIS2']['url'],
    $interopConfig['DHIS2']['user'],
    $interopConfig['DHIS2']['password']
);


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$instanceId = $general->getInstanceId();

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    COUNTRY\SOUTH_SUDAN => 'forms/init-southsudan.php'
);


require_once($fileArray[$arr['vl_form']]);
