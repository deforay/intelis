<?php

use App\Services\CommonService;

require_once(__DIR__ . "/../../../../bootstrap.php");

if (php_sapi_name() !== 'cli' && !isset($_SESSION['userId'])) {
    http_response_code(403);
    exit(0);
}

$general = new CommonService();
$arr = $general->getGlobalConfig();

// $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
// $instanceId = $instanceResult['vlsm_instance_id'];

$fileArray = array(
    7 => 'forms/rwanda/receive-rwanda.php'
);

require($fileArray[$arr['vl_form']]);
