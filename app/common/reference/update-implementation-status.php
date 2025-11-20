<?php

use Psr\Http\Message\ServerRequestInterface;
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
$tableName = "r_implementation_partners";
try {

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $status = ['i_partner_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        $db->where('i_partner_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Throwable $exc) {
    LoggerUtility::logError($exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
echo $result;
