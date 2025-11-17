<?php

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
$arr = $general->getGlobalConfig();

// $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
// $instanceId = $instanceResult['vlsm_instance_id'];

$fileArray = [7 => 'forms/rwanda/init-rwanda.php'];

//require_once($fileArray[$arr['vl_form']]);
require_once(__DIR__ . '/forms/rwanda/init-rwanda.php');
