<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_funding_sources";
try {

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = $request->getParsedBody();

    $id = explode(",", (string) $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'funding_source_status' => $_POST['status'],
            'updated_datetime'     =>  DateUtility::getCurrentDateTime(),
        );
        $db->where('funding_source_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
echo htmlspecialchars((string) $result);
