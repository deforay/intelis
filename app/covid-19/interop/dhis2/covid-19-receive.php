<?php

use const COUNTRY\SOUTH_SUDAN;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

require_once(__DIR__ . "/../../../../../bootstrap.php");

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();


// let us do init first
require_once(APPLICATION_PATH . '/covid-19/interop/dhis2/covid-19-init.php');



$instanceId = $general->getInstanceId();

$fileArray = [SOUTH_SUDAN => 'forms/receive-southsudan.php'];

require_once($fileArray[$arr['vl_form']]);
