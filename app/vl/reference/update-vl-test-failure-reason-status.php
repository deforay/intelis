<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "r_vl_test_failure_reasons";
$result = 0;
try {
    $status = [
        'status' => $_POST['status'],
        'updated_datetime' => DateUtility::getCurrentDateTime(),
    ];
    $db->where('failure_id', $_POST['id']);
    $result = $db->update($tableName, $status);
} catch (Throwable $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}
echo $result;
