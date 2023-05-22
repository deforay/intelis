<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName = "form_vl";
try {
    $id = $_POST['id'];
    $status = array(
        'contact_complete_status' => $_POST['value']
    );
    $db = $db->where('vl_sample_id', $id);
    $db->update($tableName, $status);
    $result = $id;
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo htmlspecialchars($result);
