<?php

use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "form_vl";
try {


    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    for ($i = 0; $i < count($id); $i++) {
        $status = array(
            'result_status' => $_POST['status'],
            'result_approved_datetime' => DateUtility::getCurrentDateTime(),
            'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            'data_sync' => 0
        );
        /* Check if already have reviewed and approved by */
        $db->where('vl_sample_id', $id[$i]);
        $vlRow = $db->getOne($tableName);
        if (empty($vlRow['result_reviewed_by'])) {
            $status['result_reviewed_by'] = $_SESSION['userId'];
        }
        if (empty($vlRow['result_approved_by'])) {
            $status['result_approved_by'] = $_SESSION['userId'];
        }
        if ($_POST['status'] == SAMPLE_STATUS\REJECTED) {
            $status['result_value_log'] = '';
            $status['result_value_absolute'] = '';
            $status['result_value_text'] = '';
            $status['result_value_absolute_decimal'] = '';
            $status['result'] = '';
            $status['is_sample_rejected'] = 'yes';
            $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
        } else {
            $status['is_sample_rejected'] = 'no';
        }


        $vlService = ContainerRegistry::get(VlService::class);
        $status['vl_result_category'] = $vlService->getVLResultCategory($status['result_status'], $vlRow['result']);
        if ($status['vl_result_category'] == 'failed' || $status['vl_result_category'] == 'invalid') {
            $status['result_status'] = SAMPLE_STATUS\TEST_FAILED;
        } elseif ($status['vl_result_category'] == 'rejected') {
            $status['result_status'] = SAMPLE_STATUS\REJECTED;
        }

        $db->where('vl_sample_id', $id[$i]);
        $db->update($tableName, $status);
    
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
      
        if (count($userData) > 0) {
            $db->where('vl_sample_id', $id[$i]);
            $db->update($tableName, $userData);
        }


        $result = $id[$i];

        $sampleCode = 'sample_code';
        if ($general->isSTSInstance()) {
            $sampleCode = 'remote_sample_code';
            if (!empty($vlRow['remote_sample']) && $vlRow['remote_sample'] == 'yes') {
                $sampleCode = 'remote_sample_code';
            } else {
                $sampleCode = 'sample_code';
            }
        }

        $sampleId = (isset($vlRow[$sampleCode]) && !empty($vlRow[$sampleCode])) ? ' sample id ' . $vlRow[$sampleCode] : '';
        $patientId = (isset($vlRow['patient_art_no']) && !empty($vlRow['patient_art_no'])) ? ' patient id ' . $vlRow['patient_art_no'] : '';
        $concat = (!empty($sampleId) && !empty($patientId)) ? ' and' : '';
        //Add event logs
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated VL samples status for the ' . $sampleId . $concat .  $patientId;
        $resource = 'vl-results';
        $general->activityLog($eventType, $action, $resource);
        echo $result;
    }
} catch (Throwable $e) {
    throw new SystemException(
        $e->getMessage(),
        $e->getCode(),
        $e
    );
}
