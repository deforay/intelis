<?php

$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once __DIR__ . "../../../bootstrap.php";
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    echo "WIP";
} catch (Exception $exc) {
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    error_log($exc->getMessage());
}
