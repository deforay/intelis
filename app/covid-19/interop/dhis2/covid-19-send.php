<?php

use App\Services\CommonService;

require_once(APPLICATION_PATH . '/../configs/config.interop.php');

$general = new CommonService();



$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();


$fileArray = array(
    1 => 'forms/send-southsudan.php'
);

require($fileArray[$arr['vl_form']]);
