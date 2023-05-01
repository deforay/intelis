<?php

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


// ini_set('memory_limit', -1);
// ini_set('max_execution_time', -1);
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
echo $general->getLastSyncDateTime();
