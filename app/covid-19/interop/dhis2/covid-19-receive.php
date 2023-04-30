<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

require_once(__DIR__ . "/../../../../../bootstrap.php");

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();


// let us do init first
require_once(APPLICATION_PATH . '/covid-19/interop/dhis2/covid-19-init.php');



$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$fileArray = array(
    1 => 'forms/receive-southsudan.php'
);

require($fileArray[$arr['vl_form']]);
