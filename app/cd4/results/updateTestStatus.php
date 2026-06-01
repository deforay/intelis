<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\CANCELLED;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\BulkResultStatusService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BulkResultStatusService $bulkResultStatusService */
$bulkResultStatusService = ContainerRegistry::get(BulkResultStatusService::class);

$tableName = "form_cd4";
try {


    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $db->where('cd4_id', $id[$i]);
        $vlRow = $db->getOne($tableName);
        $status = [];
        if (!empty($_POST['status'])) {
            $status = [
                'result_status' => $_POST['status'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];
            // Preserve the historic auto-fill behavior on status updates for CD4.
            // Skip for Cancelled samples since no testing/approval took place.
            if ($_POST['status'] != CANCELLED) {
                if (empty($vlRow['result_reviewed_by'])) {
                    $status['result_reviewed_by'] = $_SESSION['userId'];
                }
                if (empty($vlRow['result_approved_by'])) {
                    $status['result_approved_by'] = $_SESSION['userId'];
                }
            }
            if ($_POST['status'] == REJECTED) {
                $status['cd4_result'] = '';
                $status['cd4_result_percentage'] = '';
                $status['is_sample_rejected'] = 'yes';
                $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
            } else {
                $status['is_sample_rejected'] = 'no';
                $status['reason_for_sample_rejection'] = null;
            }

            $db->where('cd4_id', $id[$i]);
            $db->update($tableName, $status);
        }
        $result = $id[$i];

        $userData = $bulkResultStatusService->getBulkUserData($vlRow, $_POST);
        if ($userData !== []) {
            $userData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            $userData['data_sync'] = 0;

            $db->where('cd4_id', $id[$i]);
            $db->update($tableName, $userData);
        }


        //Add event log
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated VL samples status';
        $resource = 'cd4-results';
        $general->activityLog($eventType, $action, $resource);
        echo $result;
    }
} catch (Exception $e) {
    throw new SystemException($e->getMessage(), 500, $e);
}
