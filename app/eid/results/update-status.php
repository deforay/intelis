<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\BulkResultStatusService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BulkResultStatusService $bulkResultStatusService */
$bulkResultStatusService = ContainerRegistry::get(BulkResultStatusService::class);
$tableName = "form_eid";
$result = "";
try {


    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $db->where('eid_id', $id[$i]);
        $currentRow = $db->getOne($tableName, ['result_approved_by', 'tested_by', 'result_reviewed_by']);

        $status = [];
        if (!empty($_POST['status'])) {
            $status = [
                'result_status' => $_POST['status'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            if ($_POST['status'] == REJECTED) {
                $status['result'] = null;
                $status['is_sample_rejected'] = 'yes';
                $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
            } else {
                $status['is_sample_rejected'] = 'no';
                $status['reason_for_sample_rejection'] = null;
            }

            $db->where('eid_id', $id[$i]);
            $db->update($tableName, $status);
        }
        $result = $id[$i];

        $userData = $bulkResultStatusService->getBulkUserData($currentRow, $_POST);
        if ($userData !== []) {
            $userData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            $userData['data_sync'] = 0;

            $db->where('eid_id', $id[$i]);
            $db->update($tableName, $userData);
        }


        //Add event log
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated EID samples status';
        $resource = 'eid-results';
        $general->activityLog($eventType, $action, $resource);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
echo $result;
