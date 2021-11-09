<?php

require_once(__DIR__ . "/../../../startup.php");

include_once(APPLICATION_PATH . '/configs/config.interop.php');

$general = new \Vlsm\Models\General();

$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    7 => 'forms/send-rwanda.php'
);

if (file_exists($fileArray[$arr['vl_form']])) {
    require_once($fileArray[$arr['vl_form']]);
}
