<?php

use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\REJECTED;
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
$tableName = "form_eid";
$result = "";
try {


    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {

        $status = ['result_status'             => $_POST['status'], 'result_approved_datetime'  =>  DateUtility::getCurrentDateTime(), 'last_modified_datetime'     =>  DateUtility::getCurrentDateTime(), 'data_sync'                 => 0];

        /* Check if already have reviewed and approved by 
        $db->where('eid_id', $id[$i]);
        $reviewd = $db->getOne($tableName, array("result_reviewed_by", "result_approved_by"));
        if (empty($reviewd['result_reviewed_by'])) {
            $status['result_reviewed_by'] = $_SESSION['userId'];
        }
        if (empty($reviewd['result_approved_by'])) {
            $status['result_approved_by'] = $_SESSION['userId'];
        }*/
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
        $result = $id[$i];

        $userData = [];
        if($_POST['approver'] != ''){
           $userData['result_approved_by'] = $_POST['approver'];
        }
        if($_POST['tester'] != ''){
           $userData['tested_by'] = $_POST['tester'];
        }
        if($_POST['reviewer'] != ''){
            $userData['result_reviewed_by'] = $_POST['reviewer'];
        }
      
        if ($userData !== []) {
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
