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
$tableName = "lab_storage";
try {

    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $status = ['storage_status' => $_POST['status'], 'updated_datetime'     =>  DateUtility::getCurrentDateTime()];
        $db->where('storage_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
echo htmlspecialchars((string) $result);
