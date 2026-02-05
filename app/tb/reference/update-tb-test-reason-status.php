<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "r_tb_test_reasons";
try {

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $status = ['test_reason_status' => $_POST['status'], 'updated_datetime' => DateUtility::getCurrentDateTime()];
        $db->where('test_reason_id', $id[$i]);
        $db->update($tableName, $status);
        $result = $id[$i];
    }
} catch (Throwable $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
}
echo $result;
