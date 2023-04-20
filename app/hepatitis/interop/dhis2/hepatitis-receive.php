<?php

use App\Models\General;

require_once(__DIR__ . "/../../../../bootstrap.php");
require_once(APPLICATION_PATH . '/../configs/config.interop.php');

$general = new General();
$arr = $general->getGlobalConfig();

// let us do init first
require_once(APPLICATION_PATH . '/hepatitis/interop/dhis2/hepatitis-init.php');

$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
$instanceId = $instanceResult['vlsm_instance_id'];

$fileArray = array(
    7 => 'forms/receive-rwanda.php'
);


require($fileArray[$arr['vl_form']]);
