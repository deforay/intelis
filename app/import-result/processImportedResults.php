<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

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
