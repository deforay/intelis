<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$module = $_POST['module'];

if ($module == 'vl') {
    require_once(APPLICATION_PATH . '/import-result/process-vl.php');
} else if ($module == 'eid') {
    require_once(APPLICATION_PATH . '/import-result/process-eid.php');
} else if ($module == 'covid19') {
    require_once(APPLICATION_PATH . '/import-result/process-covid-19.php');
} else if ($module == 'hepatitis') {
    require_once(APPLICATION_PATH . '/import-result/process-hepatitis.php');
}
