<?php

use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$formId = (int) $general->getGlobalConfig('vl_form');

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');

// Sanitize input
$_POST = _sanitizeInput($_POST);

$tableName = "form_vl";
$tableName2 = "log_result_updates";
$vl_result_category = null;
$vlResult = null;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;
try {
    $instanceId = $general->getInstanceId();
    $testingPlatform = null;
    if (isset($_POST['testingPlatform']) && trim((string) $_POST['testingPlatform']) != '') {
        $platForm = explode("##", (string) $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }

    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true);
    $_POST['sampleReceivedAtHubOn'] = DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'] ?? '', true);
    $_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true);
    $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'] ?? '', true);
    $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($_POST['resultDispatchedOn'] ?? '', true);
    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);

    // PNG SPECIFIC
    $_POST['failedTestDate'] = DateUtility::isoDateFormat($_POST['failedTestDate'] ?? '', true);
    $_POST['qcDate'] = DateUtility::isoDateFormat($_POST['qcDate'] ?? '');
    $_POST['reportDate'] = DateUtility::isoDateFormat($_POST['reportDate'] ?? '');
    $_POST['clinicDate'] = DateUtility::isoDateFormat($_POST['clinicDate'] ?? '');
    // DRC SPECIFIC
    $_POST['dateOfCompletionOfViralLoad'] = DateUtility::isoDateFormat($_POST['dateOfCompletionOfViralLoad'] ?? '', true);


    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_vl_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = [
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            ];
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $db->getInsertId();
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }

    if ($formId == '5') {
        $_POST['vlResult'] = $_POST['finalViralLoadResult'] ?? $_POST['cphlVlResult'] ?? $_POST['vlResult'] ?? null;
    }

    // Let us process the result entered by the user
    $processedResults = $vlService->processViralLoadResultFromForm($_POST);

    $isRejected = $processedResults['isRejected'];
    $finalResult = $processedResults['finalResult'];
    $absDecimalVal = $processedResults['absDecimalVal'];
    $absVal = $processedResults['absVal'];
    $logVal = $processedResults['logVal'];
    $txtVal = $processedResults['txtVal'];
    $hivDetection = $processedResults['hivDetection'];
    $resultStatus = $processedResults['resultStatus'] ?? null;


    $reasonForChanges = null;
    $allChange = [];
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = json_decode(base64_decode((string) $_POST['reasonForResultChangesHistory']), true);
    }
    if (isset($_POST['reasonForResultChanges']) && trim((string) $_POST['reasonForResultChanges']) != '') {
        $allChange[] = array(
            'usr' => $_SESSION['userId'] ?? $_POST['userId'],
            'msg' => $_POST['reasonForResultChanges'],
            'dtime' => DateUtility::getCurrentDateTime()
        );
    }
    if (!empty($allChange)) {
        $reasonForChanges = json_encode($allChange);
    }

    if ($_POST['failedTestingTech'] != '') {
        $platForm = explode("##", (string) $_POST['failedTestingTech']);
        $_POST['failedTestingTech'] = $platForm[0];
    }


    $vlData = array(
        'vlsm_instance_id' => $instanceId,
        // 'lab_id' => $_POST['labId'] ?? null,
        'vl_test_platform' => $testingPlatform ?? null,
        'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
        'sample_received_at_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'] ?? null,
        'is_sample_rejected' => $isRejected,
        'reason_for_sample_rejection' => $_POST['rejectionReason'] ?? null,
        'rejection_on' => DateUtility::isoDateFormat($_POST['rejectionDate']),
        'result_value_absolute' => $absVal ?? null,
        'result_value_absolute_decimal' => $absDecimalVal ?? null,
        'result_value_text' => $txtVal ?? null,
        //'cphl_vl_result' => $finalResult ?? null,
        'cphl_vl_result' => $_POST['cphlVlResult'] ?? null,
        'result' => $finalResult ?? null,
        'result_value_log' => $logVal ?? null,
        'result_value_hiv_detection' => $hivDetection ?? null,
        'reason_for_failure' => $_POST['reasonForFailure'] ?? null,
        'result_reviewed_by' => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime' => $_POST['reviewedOn'] ?? null,
        'cv_number' => $_POST['cvNumber'] ?? null,
        'lab_assigned_code' => $_POST['labAssignedCode'] ?? null,
        'vl_focal_person' => $_POST['vlFocalPerson'] ?? null,
        'vl_focal_person_phone_number' => $_POST['vlFocalPersonPhoneNumber'] ?? null,
        'tested_by' => $_POST['testedBy'] ?? null,
        'result_approved_by' => $_POST['approvedBy'] ?? null,
        'result_approved_datetime' => $_POST['approvedOnDateTime'] ?? null,
        'lab_tech_comments' => $_POST['labComments'] ?? null,
        'reason_for_result_changes' => $reasonForChanges ?? null,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? ($_SESSION['userId'] ?? $_POST['userId']) : null,
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
        'last_modified_by' => $_SESSION['userId'] ?? $_POST['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'manual_result_entry' => 'yes',
        'data_sync' => 0,
        'result_printed_datetime' => null,
        'failed_test_date' => $_POST['failedTestDate'] ?? null,
        'qc_date' => $_POST['qcDate'] ?? null,
        'clinic_date' => $_POST['clinicDate'] ?? null,
        'report_date' => $_POST['reportDate'] ?? null,
        'batch_quality' => $_POST['batchQuality'] ?? null,
        'sample_test_quality' => $_POST['testQuality'] ?? null,
        'sample_batch_id' => $_POST['batchNo'] ?? null,
        'failed_test_tech' => $_POST['failedTestingTech'] ?? null,
        'failed_vl_result' => $_POST['failedvlResult'] ?? null,
        'failed_batch_quality' => $_POST['failedbatchQuality'] ?? null,
        'failed_sample_test_quality' => $_POST['failedtestQuality'] ?? null,
        'failed_batch_id' => $_POST['failedbatchNo'] ?? null,
        'tech_name_png' => $_POST['techName'] ?? null,
        'qc_tech_name' => $_POST['qcTechName'] ?? null,
        'qc_tech_sign' => $_POST['qcTechSign'] ?? null,
    );

    $formAttributes = [
        'applicationVersion' => $general->getSystemConfig('sc_version'),
        'ip_address' => $general->getClientIpAddress()
    ];

    if (isset($_POST['freezer']) && $_POST['freezer'] != "" && $_POST['freezer'] != null) {

        $freezerCheck = $general->getDataFromOneFieldAndValue('lab_storage', 'storage_id', $_POST['freezer']);

        if (empty($freezerCheck)) {
            $storageId = $general->generateUUID();
            $freezerCode = $_POST['freezer'];
            $d = [
                'storage_id' => $storageId,
                'storage_code' => $freezerCode,
                'lab_id' => $_POST['labId'],
                'storage_status' => 'active'
            ];
            $db->insert('lab_storage', $d);
        } else {
            $storageId = $_POST['freezer'];
            $condition = " storage_id = '$freezerCheck'";
            $freezerInfo = $general->getDataByTableAndFields('lab_storage', array('storage_code'), false, $condition);
            $freezerCode = $freezerInfo[0]['storage_code'];
        }

        $formAttributes['storage'] = [
            "storageId" => $storageId,
            "storageCode" => $freezerCode,
            "rack" => $_POST['rack'],
            "box" => $_POST['box'],
            "position" => $_POST['position'],
            "volume" => $_POST['volume']
        ];
    }

    $formAttributes = $general->jsonToSetString(json_encode($formAttributes), 'form_attributes');
    $vlData['form_attributes'] = $db->func($formAttributes);

    $db->where('vl_sample_id', $_POST['vlSampleId']);
    $getPrevResult = $db->getOne('form_vl');
    if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $finalResult) {
        $vlData['result_modified'] = "yes";
    } else {
        $vlData['result_modified'] = "no";
    }


    // only if result status has changed, let us update
    if (!empty($resultStatus)) {
        $vlData['result_status'] = $resultStatus;
    }

    $vlData['vl_result_category'] = $vlService->getVLResultCategory($vlData['result_status'], $vlData['result']);

    $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vlData);
    $patientId = (isset($_POST['artNo'])) ? $_POST['artNo'] : '';
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("VL result updated successfully");
        //Log result updates
        $data = array(
            'user_id' => $_SESSION['userId'],
            'vl_sample_id' => $_POST['vlSampleId'],
            'test_type' => 'vl',
            'updated_datetime' => DateUtility::getCurrentDateTime()
        );
        $db->insert($tableName2, $data);

        $eventType = 'update-vl-result';
        $action = $_SESSION['userName'] . ' updated result for the sample id ' . $_POST['sampleCode'] . ' and patient id ' . $patientId;
        $resource = 'vl-result';
        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _translate("Please try again later");
    }

    header("Location:/vl/results/vlTestResult.php");
} catch (Exception $exc) {
    throw new SystemException($exc->getMessage(), 500, $exc);
}
