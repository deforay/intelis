<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleStatusUtility;
use App\Services\CommonService;
use App\Services\BulkResultStatusService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

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
    $skippedSampleCodes = [];
    $statusUpdated = 0;

    for ($i = 0; $i < $counter; $i++) {
        $db->where('cd4_id', $id[$i]);
        $currentRow = $db->getOne($tableName, ['result_approved_by', 'tested_by', 'result_reviewed_by', 'sample_code', 'remote_sample_code', 'cd4_result']);

        $status = [];
        // A status that says the test is finished cannot go onto a sample
        // with no result: the printing, dispatch and reporting queries all
        // read Accepted as "there is a result here", so such a row drops out
        // of every one of them at once. The rest of the selection still goes
        // through, because a selection is a list of separate patients and one
        // missing result is no reason to hold up anyone else's.
        $cannotAccept = SampleStatusUtility::assertsAResult($_POST['status'] ?? null)
            && !SampleStatusUtility::rowHasResult('cd4', $currentRow ?? []);
        if ($cannotAccept) {
            $skippedSampleCodes[] = ($currentRow['sample_code'] ?? '')
                ?: ($currentRow['remote_sample_code'] ?? '')
                ?: $id[$i];
        }

        if (!empty($_POST['status']) && !$cannotAccept) {
            $status = [
                'result_status' => $_POST['status'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];

            if ($_POST['status'] == REJECTED) {
                // form_cd4 holds the result in cd4_result; writing `result` here
                // named a column that does not exist, so the whole rejection
                // update failed and the error went to the log unread.
                $status['cd4_result'] = null;
                $status['cd4_result_percentage'] = null;
                $status['is_sample_rejected'] = 'yes';
                $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
            } else {
                $status['is_sample_rejected'] = 'no';
                $status['reason_for_sample_rejection'] = null;
            }

            $db->where('cd4_id', $id[$i]);
            $db->update($tableName, $status);
            $statusUpdated++;
        }

        $userData = $bulkResultStatusService->getBulkUserData($currentRow, $_POST);
        if ($userData !== []) {
            $userData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            $userData['data_sync'] = 0;

            $db->where('cd4_id', $id[$i]);
            $db->update($tableName, $userData);
        }

        //Add event log
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated CD4 samples status';
        $resource = 'cd4-results';
        $general->activityLog($eventType, $action, $resource);
    }

    $bulkCompleted = true;
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
header('Content-Type: application/json');
echo json_encode(
    empty($bulkCompleted)
        ? $bulkResultStatusService->failureResponse()
        : $bulkResultStatusService->buildResponse($statusUpdated, $skippedSampleCodes)
);
