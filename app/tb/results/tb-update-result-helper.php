<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\GeoLocationsService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$tableName = "form_tb";
$tableName1 = "activity_log";
$testTableName = 'tb_tests';

try {
    //system config
    $systemConfigQuery = "SELECT * FROM system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    if (!empty($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) != "") {
        $sampleCollectionDate = explode(" ", (string) $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }

    //Set sample received date
    if (!empty($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", (string) $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    if (!empty($_POST['resultDispatchedDatetime']) && trim((string) $_POST['resultDispatchedDatetime']) != "") {
        $resultDispatchedDatetime = explode(" ", (string) $_POST['resultDispatchedDatetime']);
        $_POST['resultDispatchedDatetime'] = DateUtility::isoDateFormat($resultDispatchedDatetime[0]) . " " . $resultDispatchedDatetime[1];
    } else {
        $_POST['resultDispatchedDatetime'] = null;
    }
    if (!empty($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) != "") {
        $sampleTestedDate = explode(" ", (string) $_POST['sampleTestedDateTime']);
        $_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestedDateTime'] = null;
    }

    if (!empty($_POST['arrivalDateTime']) && trim((string) $_POST['arrivalDateTime']) != "") {
        $arrivalDate = explode(" ", (string) $_POST['arrivalDateTime']);
        $_POST['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['arrivalDateTime'] = null;
    }

    if (!empty($_POST['requestedDate']) && trim((string) $_POST['requestedDate']) != "") {
        $arrivalDate = explode(" ", (string) $_POST['requestedDate']);
        $_POST['requestedDate'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['requestedDate'] = null;
    }

    if (empty(trim((string) $_POST['sampleCode']))) {
        $_POST['sampleCode'] = null;
    }

    if ($_SESSION['instanceType'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    $status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    }

    $resultSentToSource = null;

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['result'] = null;
        $status = SAMPLE_STATUS\REJECTED;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['patientDob'])) {
        $_POST['patientDob'] = DateUtility::isoDateFormat($_POST['patientDob']);
    }

    if (!empty($_POST['result'])) {
        $resultSentToSource = 'pending';
    }

    if (isset($_POST['reviewedOn']) && trim((string) $_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", (string) $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if (isset($_POST['approvedOn']) && trim((string) $_POST['approvedOn']) != "") {
        $approvedOn = explode(" ", (string) $_POST['approvedOn']);
        $_POST['approvedOn'] = DateUtility::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = null;
    }
    if (isset($_POST['province']) && $_POST['province'] != "") {
        $province = explode("##", (string) $_POST['province']);
        $_POST['provinceId'] = $geolocationService->getProvinceIdByName($province[0]);
    }

    if (isset($_POST['resultDate']) && trim((string) $_POST['resultDate']) != "") {
        $resultDate = explode(" ", (string) $_POST['resultDate']);
        $_POST['resultDate'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['resultDate'] = null;
    }

    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
					FROM r_tb_sample_rejection_reasons
					WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            );
            $id = $db->insert('r_tb_sample_rejection_reasons', $data);
            $_POST['sampleRejectionReason'] = $id;
        } else {
            $_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }

    $tbData = array(
        //'specimen_quality'                    => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
        'lab_id' => !empty($_POST['labId']) ? $_POST['labId'] : null,
        //'reason_for_tb_test'                  => !empty($_POST['reasonForTbTest']) ? json_encode($_POST['reasonForTbTest']) : null,
        //'tests_requested'                     => !empty($_POST['testTypeRequested']) ? json_encode($_POST['testTypeRequested']) : null,
        //'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
        //  'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
        'result_date' => !empty($_POST['resultDate']) ? $_POST['resultDate'] : null,
        'sample_received_at_lab_datetime' => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
        'is_sample_rejected' => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,
        'result' => !empty($_POST['result']) ? $_POST['result'] : null,
        'xpert_mtb_result' => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'result_sent_to_source' => $resultSentToSource,
        'result_dispatched_datetime' => !empty($_POST['resultDispatchedDatetime']) ? $_POST['resultDispatchedDatetime'] : null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime' => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'tested_by' => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
        'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status' => $status,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'recommended_corrective_action' => (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) != '') ? $_POST['correctiveAction'] : null,
        'sample_registered_at_lab' => DateUtility::getCurrentDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'request_created_by' => $_SESSION['userId'],
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId']
    );
    //echo '<pre>'; print_r($tbData); die;
    $id = 0;

    if (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (!empty($_POST['testResult'])) {
            $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);

            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (!empty($testResult) && trim((string) $testResult) != "") {
                    $db->insert(
                        $testTableName,
                        array(
                            'tb_id' => $_POST['tbSampleId'],
                            'actual_no' => $_POST['actualNo'][$testKey] ?? null,
                            'test_result' => $testResult,
                            'updated_datetime' => DateUtility::getCurrentDateTime()
                        )
                    );
                }
            }
        }
    } else {
        $db->where('tb_id', $_POST['tbSampleId']);
        $db->delete($testTableName);
    }

    if (!empty($_POST['tbSampleId'])) {
        $db->where('tb_id', $_POST['tbSampleId']);
        $id = $db->update($tableName, $tbData);
        error_log($db->getLastError());
    }

    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("TB test request updated successfully");
        //Add event log
        $eventType = 'tb-add-request';
        $action = $_SESSION['userName'] . ' pdated a TB request with the Sample ID/Code  ' . $_POST['tbSampleId'];
        $resource = 'tb-add-request';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _translate("Unable to update this TB sample. Please try again later");
    }

    if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
        header("Location:/tb/results/tb-update-result.php?id=" . base64_encode((string) $_POST['tbSampleId']));
    } else {
        header("Location:/tb/results/tb-manual-results.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
