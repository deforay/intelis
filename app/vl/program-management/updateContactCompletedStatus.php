<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "form_vl";
try {
    $id = $_POST['id'];
    $status = ['contact_complete_status' => $_POST['value']];
    $db->where('vl_sample_id', $id);
    $db->update($tableName, $status);
    $result = $id;
} catch (Throwable $exc) {
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => $exc->getFile(),
        'line' => $exc->getLine(),
        'trace' => $exc->getTraceAsString(),
    ]);
}
echo htmlspecialchars((string) $result);
