<?php

$cliMode = php_sapi_name() === 'cli';

if ($cliMode) {
    require_once __DIR__ . "../../../bootstrap.php";
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {


    $tableName = TestsService::getTestTableName('vl');
    $resultStatus = [
        SAMPLE_STATUS\REJECTED,
        SAMPLE_STATUS\ACCEPTED
    ];
    $db->where("(vl.result IS NOT NULL AND vl.result != '')
                    OR IFNULL(vl.is_sample_rejected, 'no') = 'yes'");
    $db->where("IFNULL(vl.result_sent_to_external, 'no') = 'no'");
    $db->where("result_status", $resultStatus, 'IN');

    $db->get($tableName);
} catch (Exception $exc) {
    error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
    error_log($exc->getMessage());
}
