<?php

use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;
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
    $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($_POST['sampleCollectionDate'] ?? '', true);
    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true);
    $_POST['resultDispatchedDatetime'] = DateUtility::isoDateFormat($_POST['resultDispatchedDatetime'] ?? '', true);
    $_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($_POST['sampleTestedDateTime'] ?? '', true);
    $_POST['arrivalDateTime'] = DateUtility::isoDateFormat($_POST['arrivalDateTime'] ?? '', true);
    $_POST['requestedDate'] = DateUtility::isoDateFormat($_POST['requestedDate'] ?? '', true);


    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    $status = RECEIVED_AT_TESTING_LAB;
    if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
        $status = RECEIVED_AT_CLINIC;
    }

    $resultSentToSource = null;

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['finalResult'] = null;
        $status = REJECTED;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['dob'])) {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob'] ?? '');
    }

    if (!empty($_POST['finalResult'])) {
        $resultSentToSource = 'pending';
    }

    if (isset($_POST['province']) && $_POST['province'] != "") {
        $province = explode("##", (string) $_POST['province']);
        $_POST['provinceId'] = $geolocationService->getProvinceIdByName($province[0]);
    }

    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
    $_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);
    $_POST['resultDate'] = DateUtility::isoDateFormat($_POST['resultDate'] ?? '', true);
    $_POST['xpertDateOfResult'] = DateUtility::isoDateFormat($_POST['xpertDateOfResult'] ?? '', true);
    $_POST['tbLamDateOfResult'] = DateUtility::isoDateFormat($_POST['tbLamDateOfResult'] ?? '', true);
    $_POST['cultureDateOfResult'] = DateUtility::isoDateFormat($_POST['cultureDateOfResult'] ?? '', true);
    $_POST['identificationDateOfResult'] = DateUtility::isoDateFormat($_POST['identificationDateOfResult'] ?? '', true);
    $_POST['drugMGITDateOfResult'] = DateUtility::isoDateFormat($_POST['drugMGITDateOfResult'] ?? '', true);
    $_POST['drugLPADateOfResult'] = DateUtility::isoDateFormat($_POST['drugLPADateOfResult'] ?? '', true);


    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
					FROM r_tb_sample_rejection_reasons
					WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = ['rejection_reason_name' => $_POST['newRejectionReason'], 'rejection_type' => 'general', 'rejection_reason_status' => 'active', 'updated_datetime' => DateUtility::getCurrentDateTime()];
            $id = $db->insert('r_tb_sample_rejection_reasons', $data);
            $_POST['sampleRejectionReason'] = $id;
        } else {
            $_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }
    if ($_POST['formId'] == COUNTRY\RWANDA) {
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
    if (is_array($_POST['purposeOfTbTest'])) {
        $_POST['purposeOfTbTest'] = implode(",", $_POST['purposeOfTbTest']);
    }
    if (!empty($_POST['tbTestsRequested']) && is_array($_POST['tbTestsRequested'])) {
        $_POST['tbTestsRequested'] = implode(",", $_POST['tbTestsRequested']);
    }
    $tbData = [
        'tests_requested' => empty($_POST['tbTestsRequested']) ? null : $_POST['tbTestsRequested'],
        'affiliated_district_hospital' => empty($_POST['affiliatedDistrictHospital']) ? null : $_POST['affiliatedDistrictHospital'],
        'lab_id' => empty($_POST['labId']) ? $_POST['testResult']['labId'][0] : $_POST['labId'],
        'result_date' => empty($_POST['resultDate']) ? null : $_POST['resultDate'],
        'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
        'is_sample_rejected' => empty($_POST['isSampleRejected']) ? null : $_POST['isSampleRejected'],
        'result' => $_POST['finalResult'] ?? null,
        'tb_lam_result' => $_POST['tbLamResult'] ?? null,
        'xpert_mtb_result' => empty($_POST['xPertMTMResult']) ? null : $_POST['xPertMTMResult'],
        'culture_result' => empty($_POST['cultureResult']) ? null : $_POST['cultureResult'],
        'identification_result' => empty($_POST['identicationResult']) ? null : $_POST['identicationResult'],
        'drug_mgit_result' => empty($_POST['drugMGITResult']) ? null : $_POST['drugMGITResult'],
        'drug_lpa_result' => empty($_POST['drugLPAResult']) ? null : $_POST['drugLPAResult'],
        'xpert_result_date' => empty($_POST['xpertDateOfResult']) ? null : $_POST['xpertDateOfResult'],
        'culture_result_date' => empty($_POST['cultureDateOfResult']) ? null : $_POST['cultureDateOfResult'],
        'tblam_result_date' => $_POST['tbLamDateOfResult'] ?? null,
        'identification_result_date' => $_POST['identificationDateOfResult'] ?? null,
        'drug_mgit_result_date' => empty($_POST['drugMGITDateOfResult']) ? null : $_POST['drugMGITDateOfResult'],
        'drug_lpa_result_date' => empty($_POST['drugLPADateOfResult']) ? null : $_POST['drugLPADateOfResult'],
        'result_sent_to_source' => $resultSentToSource,
        'result_dispatched_datetime' => empty($_POST['resultDispatchedDatetime']) ? null : $_POST['resultDispatchedDatetime'],
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime' => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'tested_by' => empty($_POST['testedBy']) ? null : $_POST['testedBy'],
        'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status' => $status,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'recommended_corrective_action' => (isset($_POST['correctiveAction']) && trim((string) $_POST['correctiveAction']) !== '') ? $_POST['correctiveAction'] : null,
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId'],
    ];
    if (isset($_POST['finalResult']) && !empty($_POST['finalResult'])) {
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
                            'is_sample_rejected' => $testResult['isSampleRejected'][$key] ?? null,
                            'reason_for_sample_rejection' => $testResult['sampleRejectionReason'][$key] ?? null,
                            'rejection_on' => DateUtility::isoDateFormat($testResult['rejectionDate'][$key] ?? null),
                            'test_type' => $testResult['testType'][$key] ?? null,
                            'test_result' => $testResult['testResult'][$key] ?? null,
                            'sample_tested_datetime' => DateUtility::isoDateFormat($testResult['sampleTestedDateTime'][$key] ?? null, true),
                            'tested_by' => $testResult['testedBy'][$key] ?? null,
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
    } elseif (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (!empty($_POST['testResult'])) {
            $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);

            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (!empty($testResult) && trim((string) $testResult) !== "") {
                    $db->insert(
                        $testTableName,
                        ['tb_id' => $_POST['tbSampleId'], 'actual_no' => $_POST['actualNo'][$testKey] ?? null, 'test_result' => $testResult, 'updated_datetime' => DateUtility::getCurrentDateTime()]
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
    }

    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("TB test result updated successfully");
        //Add event log
        $eventType = 'tb-update-result';
        $action = $_SESSION['userName'] . ' updated result for TB Sample ID/Code  ' . $_POST['tbSampleId'];
        $resource = 'tb-update-result';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _translate("Unable to update this TB result. Please try again later");
    }

    header("Location:/tb/results/tb-manual-results.php");
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
