<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName = "r_vl_test_failure_reasons";
$result = 0;
try {
    $status = array(
        'status'            => $_POST['status'],
        'updated_datetime'  =>  DateUtility::getCurrentDateTime(),
    );
    $db = $db->where('failure_id', $_POST['id']);
    $result = $db->update($tableName, $status);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo $result;
