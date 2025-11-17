<?php

use const COUNTRY\SOUTH_SUDAN;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

require_once(APPLICATION_PATH . '/../configs/config.interop.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);



$instanceId = $general->getInstanceId();

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = [SOUTH_SUDAN => 'forms/send-southsudan.php'];

require_once($fileArray[$arr['vl_form']]);
