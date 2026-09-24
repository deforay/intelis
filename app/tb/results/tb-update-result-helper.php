<?php

use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Services\TestAttemptService;
use App\Services\TbTestsService;

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
    // Acting as a LIS: a result can only be recorded for this install's own lab.
    // A sample already saved against another lab (referred in) keeps that lab, so
    // this never reassigns someone else's sample. No-op on STS and standalone.
    $_POST['labId'] = $general->resolveRequestLabId($_POST['labId'] ?? null, $tableName, 'tb_id', $_POST['tbSampleId'] ?? null);
    // Per-test cards follow the same rule, except a lab that already has tests
    // on this sample (referred in) keeps them. Blank cards stay blank.
    if (!empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId'])) {
        $_POST['testResult']['labId'] = $general->resolveTestCardLabIds($_POST['testResult']['labId'], $_POST['labId'] ?? null, 'tb_tests', 'tb_id', $_POST['tbSampleId'] ?? null);
    }

    $db->where('tb_id', $_POST['tbSampleId'] ?? 0);
    $sampleFacilityId = (int) ($db->getValue($tableName, 'facility_id') ?? 0);
    $general->assertFacilityAllowed($sampleFacilityId);

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

    $resultSentToSource = null;

    // A result is kept only when the form says it is finalized. A form without that
    // question keeps the final interpretation it posted. Decided before the status,
    // so a result that is not kept does not send the sample for approval.
    if (
        trim((string) ($_POST['finalResult'] ?? '')) === ''
        || (array_key_exists('isResultFinalized', $_POST) && $_POST['isResultFinalized'] != 'yes')
    ) {
        $_POST['finalResult'] = null;
    }

    // null means leave result_status alone. Awaiting Approval is reached only when
    // a final result was entered, and once one has been the sample cannot go back to
    // Received at Testing Lab -- so a save carrying no result must not rewrite the
    // status at all. It previously defaulted to RECEIVED_AT_TESTING_LAB and always
    // wrote it, which sent an already-approved sample backwards on any re-save.
    //
    // trim() rather than !empty(): a final result of "0" is a result.
    // Rejection is a verdict on the sample, not on one test card. A TB sample is one
    // specimen transferred from lab to lab, so a rejected specimen is rejected for
    // every test on the request -- every form posts the one flat isSampleRejected,
    // and form_tb is the only place any reader looks for it.
    $status = null;
    $sampleRejected = $_POST['isSampleRejected'] ?? null;
    if ($sampleRejected === 'yes') {
        $_POST['finalResult'] = null;
        $status = REJECTED;
        $resultSentToSource = 'pending';
    } elseif (trim((string) ($_POST['finalResult'] ?? '')) !== '') {
        $status = PENDING_APPROVAL; // Awaiting Approval
    }

    // Un-rejecting is the one case where a save with no result must still move the
    // sample. is_sample_rejected is written unconditionally below, so leaving the
    // status alone would clear the rejection while the sample stayed at REJECTED --
    // off the worklist, and not rejected either. Only read when it can matter.
    $markedNotRejected = $sampleRejected === 'no';

    if ($status === null && $markedNotRejected) {
        // Lab-scoped, so this cannot read a sample belonging to another lab.
        $db->where('tb_id', $_POST['tbSampleId'] ?? 0);
        if ($labScope = $general->labScopeWhere('form_tb')) {
            $db->where($labScope);
        }
        if ((int) $db->getValue($tableName, 'result_status') === REJECTED) {
            $status = ($general->isSTSInstance() && ($_SESSION['accessType'] ?? '') === 'collection-site')
                ? RECEIVED_AT_CLINIC
                : RECEIVED_AT_TESTING_LAB;
        }
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
// Which of these the form actually sent, recorded before the lines below can add
// a key -- the approved-on normalisation uses ?? '' and would create an absent
// one. A result form that does not render a field must not blank it: the PNG
// forms carry no approver, and writing NULL there erases what the request edit
// recorded. Keyed on what was posted, not on a country list.
$resultColumnsOwnedByTheForm = [
    'result_approved_by'       => 'approvedBy',
    'result_approved_datetime' => 'approvedOn',
    'lab_tech_comments'        => 'labComments',
];
$resultColumnsPosted = [];
foreach ($resultColumnsOwnedByTheForm as $column => $postKey) {
    if (array_key_exists($postKey, (array) $_POST)) {
        $resultColumnsPosted[] = $column;
    }
}

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
    if (is_array($_POST['tbTestsRequested'])) {
        $_POST['tbTestsRequested'] = json_encode($_POST['tbTestsRequested']);
    }

     $labId = null;
    if (isset($_POST['labId']) && !empty($_POST['labId'])) {
        $labId = $_POST['labId'];
    } else if (isset($_POST['testResult']['labId'][0]) && !empty($_POST['testResult']['labId'][0])) {
        $labId = $_POST['testResult']['labId'][0];
    }
    $tbData = [
        //'tests_requested' => empty($_POST['tbTestsRequested']) ? null : $_POST['tbTestsRequested'],
        'affiliated_district_hospital' => empty($_POST['affiliatedDistrictHospital']) ? null : $_POST['affiliatedDistrictHospital'],
        //'lab_id' => $labId,
        'result_date' => empty($_POST['resultDate']) ? null : $_POST['resultDate'],
        'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
        'is_sample_rejected' => empty($sampleRejected) ? null : $sampleRejected,
        'is_result_finalized' => $_POST['isResultFinalized'] ?? null,
        'result' => $_POST['finalResult'],
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
        'rejection_on' => (!empty($_POST['rejectionDate']) && $sampleRejected === 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $sampleRejected === 'yes') ? $_POST['sampleRejectionReason'] : null,
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
    if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['finalResult']) {
        $tbData['result_modified'] = "yes";
    } else {
        $tbData['result_modified'] = "no";
    }
    // Retain the outgoing result before anything below mutates it. This has to run here
    // rather than next to the form update, because tb_tests is written further down and
    // that table carries no audit triggers. Nothing is written when there is no prior
    // result to keep. The kept copy, the tests and the sample are one change.
    /** @var TestAttemptService $attempts */
    $attempts = ContainerRegistry::get(TestAttemptService::class);
    $db->beginTransaction();
    $attempts->archive('tb', (int) $_POST['tbSampleId'], TestAttemptService::BY_RESULT_EDIT);

//echo "<pre>"; print_r($tbData); die;
    $id = 0;

    /**
     * TB Test Data Handling Logic:
     *
     * This system supports two types of TB forms:
     *
     * 1. MULTIPLE TESTS PER SAMPLE (per-test form):
     *    - Form sends nested array: testResult[fieldName][], one card per test
     *    - Each test has its own lab, specimen type, reviewer, approver, etc.
     *    - Tests are stored in `tb_tests` table (one row per test), saved in place:
     *      a card updates its own row, and a saved test is deleted only when its card
     *      was removed (deletedTestIds[]). A test the page did not show -- one an
     *      analyzer added while it was open -- is kept.
     *    - The LATEST test's details (latest tested date) are also stored in `form_tb`
     *
     * 2. SINGLE TEST PER SAMPLE (single-result forms):
     *    - Form sends flat array: testResult[] (microscopy results only)
     *    - Test-level fields (reviewer, approver, etc.) are direct POST fields
     *    - The Xpert result and everything else goes directly to `form_tb`
     *    - `tb_tests` holds only the microscopy rows, rebuilt from what was posted
     *
     * Detection: If testResult[labId][] exists as array = multiple tests. A form
     * that posts no testResult at all leaves `tb_tests` alone.
     */
    $hasMultipleTests = !empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId']);

    $latestTestColumns = [];
    if ($hasMultipleTests || !empty($_POST['deletedTestIds'])) {
        /** @var TbTestsService $tbTests */
        $tbTests = ContainerRegistry::get(TbTestsService::class);
        $tbTests->saveCards(
            (int) $_POST['tbSampleId'],
            (array) ($_POST['testResult'] ?? []),
            (array) ($_POST['deletedTestIds'] ?? []),
            $_SESSION['userId'] ?? null
        );
        $latestTestColumns = $tbTests->latestTestColumns((int) $_POST['tbSampleId']);
    } elseif (isset($_POST['testResult']) && is_array($_POST['testResult'])) {
        ContainerRegistry::get(TbTestsService::class)->saveMicroscopyRows(
            (int) $_POST['tbSampleId'],
            $_POST['testResult'],
            (array) ($_POST['actualNo'] ?? []),
            $_POST['labId'] ?? null
        );
    }

    if (!empty($_POST['tbSampleId'])) {
        $db->where('tb_id', $_POST['tbSampleId']);
        // Written only when this save decided a status: a result was entered, or the
        // sample was rejected. Left out otherwise, so a save with no result does not
        // move the sample back down the workflow.
        if ($status !== null) {
            $tbData['result_status'] = $status;
        }

        // Drop every one this form did not send.
        foreach ($resultColumnsOwnedByTheForm as $column => $postKey) {
            if (!in_array($column, $resultColumnsPosted, true)) {
                unset($tbData[$column]);
            }
        }
        // The latest test's details come from the tests, whatever the form posted
        // at the sample level.
        $tbData = array_merge($tbData, $latestTestColumns);
        // Neither the latest test nor the form gives a received date: the sample keeps its own.
        if ($hasMultipleTests && !isset($latestTestColumns['sample_received_at_lab_datetime'])
            && empty($tbData['sample_received_at_lab_datetime'])) {
            unset($tbData['sample_received_at_lab_datetime']);
        }

        $id = $db->update($tableName, $tbData);
    }
    // The tests are kept only with the sample they belong to.
    $id === true ? $db->commitTransaction() : $db->rollbackTransaction();

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
    $db->rollbackTransaction();
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
