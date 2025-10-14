<?php

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

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$instanceId = $general->getInstanceId();

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    7 => 'forms/init-rwanda.php',
);

require_once($fileArray[$arr['vl_form']]);
