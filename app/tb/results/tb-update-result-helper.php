<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;





/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$tableName = "form_tb";
$tableName1 = "activity_log";
$testTableName = 'tb_tests';

try {
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

    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    $status = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
    if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
        $status = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    }

    $resultSentToSource = null;

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['result'] = null;
        $status = SAMPLE_STATUS\REJECTED;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['dob'])) {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
    }

    if (!empty($_POST['result'])) {
        $resultSentToSource = 'pending';
    }

    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
    $_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);

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


    if (isset($_POST['xpertDateOfResult']) && trim((string) $_POST['xpertDateOfResult']) != "") {
        $xpertresultDate = explode(" ", (string) $_POST['xpertDateOfResult']);
        $_POST['xpertDateOfResult'] = DateUtility::isoDateFormat($xpertresultDate[0]) . " " . $xpertresultDate[1];
    } else {
        $_POST['xpertDateOfResult'] = null;
    }

    if (isset($_POST['tbLamDateOfResult']) && trim((string) $_POST['tbLamDateOfResult']) != "") {
        $resultDate = explode(" ", (string) $_POST['tbLamDateOfResult']);
        $_POST['tbLamDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['tbLamDateOfResult'] = null;
    }

    if (isset($_POST['cultureDateOfResult']) && trim((string) $_POST['cultureDateOfResult']) != "") {
        $resultDate = explode(" ", (string) $_POST['cultureDateOfResult']);
        $_POST['cultureDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['cultureDateOfResult'] = null;
    }

    if (isset($_POST['identificationDateOfResult']) && trim((string) $_POST['identificationDateOfResult']) != "") {
        $resultDate = explode(" ", (string) $_POST['identificationDateOfResult']);
        $_POST['identificationDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['identificationDateOfResult'] = null;
    }

    if (isset($_POST['drugMGITDateOfResult']) && trim((string) $_POST['drugMGITDateOfResult']) != "") {
        $resultDate = explode(" ", (string) $_POST['drugMGITDateOfResult']);
        $_POST['drugMGITDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['drugMGITDateOfResult'] = null;
    }

    if (isset($_POST['drugLPADateOfResult']) && trim((string) $_POST['drugLPADateOfResult']) != "") {
        $resultDate = explode(" ", (string) $_POST['drugLPADateOfResult']);
        $_POST['drugLPADateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['drugLPADateOfResult'] = null;
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
    if ($_POST['formId'] == 7) {
        $_POST['labId'] = $_POST['testResult']['labId'][0];
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['testResult']['sampleReceivedDate'][0] ?? null);
        $_POST['isSampleRejected'] = $_POST['testResult']['isSampleRejected'][0];
        $_POST['rejectionDate'] = $_POST['testResult']['rejectionDate'][0];
        $_POST['sampleRejectionReason'] = $_POST['testResult']['sampleRejectionReason'][0];
        $_POST['reviewedBy'] = $_POST['testResult']['reviewedBy'][0];
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['testResult']['reviewedOn'][0] ?? null);
        $_POST['approvedBy'] = $_POST['testResult']['approvedBy'][0];
        $_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['testResult']['approvedOn'][0] ?? null);
    }
    $tbData = array(
        //'specimen_quality'                    => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
        'lab_id' => !empty($_POST['labId']) ? $_POST['labId'] : $_POST['testResult']['labId'][0],
        //'reason_for_tb_test'                  => !empty($_POST['reasonForTbTest']) ? json_encode($_POST['reasonForTbTest']) : null,
        //'tests_requested'                     => !empty($_POST['testTypeRequested']) ? json_encode($_POST['testTypeRequested']) : null,
        //'specimen_type'                       => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
        //  'sample_collection_date'              => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
        'result_date' => !empty($_POST['resultDate']) ? $_POST['resultDate'] : null,
        'sample_received_at_lab_datetime' => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
        'is_sample_rejected' => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : null,

        'xpert_mtb_result' => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'culture_result' => !empty($_POST['cultureResult']) ? $_POST['cultureResult'] : null,
        'identification_result' => !empty($_POST['identicationResult']) ? $_POST['identicationResult'] : null,
        'drug_mgit_result' => !empty($_POST['drugMGITResult']) ? $_POST['drugMGITResult'] : null,
        'drug_lpa_result' => !empty($_POST['drugLPAResult']) ? $_POST['drugLPAResult'] : null,
        'xpert_result_date' => !empty($_POST['xpertDateOfResult']) ? $_POST['xpertDateOfResult'] : null,
        'culture_result_date' => !empty($_POST['cultureDateOfResult']) ? $_POST['cultureDateOfResult'] : null,
        'tblam_result_date' => !empty($_POST['tbLamDateOfResult']) ? $_POST['tbLamDateOfResult'] : null,
        'identification_result_date' => !empty($_POST['identificationDateOfResult']) ? $_POST['identificationDateOfResult'] : null,
        'drug_mgit_result_date' => !empty($_POST['drugMGITDateOfResult']) ? $_POST['drugMGITDateOfResult'] : null,
        'drug_lpa_result_date' => !empty($_POST['drugLPADateOfResult']) ? $_POST['drugLPADateOfResult'] : null,

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

        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'request_created_by' => $_SESSION['userId'],
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId']
    );
    if (isset($_POST['referLabId']) && !empty($_POST['referLabId']) && !isset($_POST['finalResult']) || empty($_POST['finalResult'])) {
        $labId = !empty($_POST['labId']) ? $_POST['labId'] : $_POST['testResult']['labId'][0];
        $tbData['referred_by_lab_id'] = $labId;
        $tbData['referred_to_lab_id'] = (!empty($_POST['referLabId']) && $_POST['referLabId'] != $labId) ? $_POST['referLabId'] : null;
        $tbData['reason_for_referral'] = !empty($_POST['reasonForReferrel']) ? $_POST['reasonForReferrel'] : null;
        $tbData['referred_on_datetime'] = DateUtility::getCurrentDateTime();
        $tbData['referred_by'] = $_SESSION['userId'];
    } else if (isset($_POST['finalResult']) && !empty($_POST['finalResult'])) {
        $tbData['result'] = $_POST['finalResult'];
    }
    $db->where('tb_id', $_POST['tbSampleId']);
    $getPrevResult = $db->getOne('form_tb');
    if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['result']) {
        $tbData['result_modified'] = "yes";
    } else {
        $tbData['result_modified'] = "no";
    }

    $id = 0;

    if ($_POST['formId'] == 7) {
        if (!empty($_POST['testResult'])) {
            $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);
            foreach ($_POST['testResult']['labId'] as $key => $labid) {
                $testResult = $_POST['testResult'];
                if (!empty($testResult['labId'])) {
                    $db->insert(
                        $testTableName,
                        [
                            'tb_id' => $_POST['tbSampleId'] ?? null,
                            'lab_id' => $testResult['labId'][$key] ?? null,
                            'specimen_type' => $testResult['specimenType'][$key] ?? null,
                            'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($testResult['sampleReceivedDate'][$key] ?? null, true),
                            'is_sample_rejected' => $testResult['isSampleRejected'][$key] ?? 'no',
                            'reason_for_sample_rejection' => $testResult['sampleRejectionReason'][$key] ?? null,
                            'rejection_on' => DateUtility::isoDateFormat($testResult['rejectionDate'][$key] ?? null),
                            'test_type' => $testResult['testType'][$key] ?? null,
                            'test_result' => $testResult['testResult'][$key] ?? null,
                            'sample_tested_datetime' => DateUtility::isoDateFormat($testResult['sampleTestedDateTime'][$key] ?? null, true),
                            'result_reviewed_by' => $testResult['reviewedBy'][$key] ?? null,
                            'result_reviewed_datetime' => DateUtility::isoDateFormat($testResult['reviewedOn'][$key] ?? null, true),
                            'result_approved_by' => $testResult['approvedBy'][$key] ?? null,
                            'result_approved_datetime' => DateUtility::isoDateFormat($testResult['approvedOn'][$key] ?? null, true),
                            'revised_by' => $testResult['revisedBy'][$key] ?? null,
                            'revised_on' => DateUtility::isoDateFormat($testResult['revisedOn'][$key] ?? null, true),
                            'comments' => $testResult['comments'][$key] ?? null,
                            'reason_for_result_change' => $testResult['comments'][$key] ?? null,
                            'updated_datetime' => DateUtility::getCurrentDateTime()
                        ]
                    );
                }
            }
        }
    } else {
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
    }

    if (!empty($_POST['tbSampleId'])) {
        $db->where('tb_id', $_POST['tbSampleId']);
        $id = $db->update($tableName, $tbData);
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
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
