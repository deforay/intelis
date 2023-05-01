<?php


use App\Interop\Dhis2;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$interopConfig = require(APPLICATION_PATH . '/../configs/config.interop.php');

$dhis2 = new Dhis2(
    $interopConfig['DHIS2']['url'],
    $interopConfig['DHIS2']['user'],
    $interopConfig['DHIS2']['password']
);


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    1 => 'forms/init-southsudan.php'
);


require($fileArray[$arr['vl_form']]);
