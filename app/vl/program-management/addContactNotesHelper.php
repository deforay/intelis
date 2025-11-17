<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "vl_contact_notes";

try {
    $result = '';
    if (isset($_POST['notes']) && trim((string) $_POST['notes']) !== "") {
        $data = ['contact_notes' => $_POST['notes'], 'treament_contact_id' => $_POST['treamentId'], 'collected_on' => DateUtility::isoDateFormat($_POST['dateVal']), 'added_on' => DateUtility::getCurrentDateTime()];
        //print_r($data);die;
        $result = $db->insert($tableName, $data);
    }
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage());
}
echo $result;
