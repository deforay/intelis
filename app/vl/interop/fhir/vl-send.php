<?php

use const COUNTRY\RWANDA;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

require_once(__DIR__ . "/../../../../bootstrap.php");

if (php_sapi_name() !== 'cli' && !isset($_SESSION['userId'])) {
    http_response_code(403);
    exit(0);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$instanceId = $general->getInstanceId();

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = [RWANDA => 'forms/rwanda/send-rwanda.php'];


require_once($fileArray[$arr['vl_form']]);
