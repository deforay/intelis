<?php


$interopConfig = require(APPLICATION_PATH . '/../configs/config.interop.php');

$dhis2 = new \App\Interop\Dhis2(
    $interopConfig['DHIS2']['url'],
    $interopConfig['DHIS2']['user'],
    $interopConfig['DHIS2']['password']
);


$general = new \App\Models\General();


$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    1 => 'forms/init-southsudan.php'
);


require($fileArray[$arr['vl_form']]);
