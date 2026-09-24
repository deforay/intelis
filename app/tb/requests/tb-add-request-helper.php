<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Services\TbTestsService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

try {

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var GeoLocationsService $geolocationService */
    $geolocationService = ContainerRegistry::get(GeoLocationsService::class);

    /** @var PatientsService $patientsService */
    $patientsService = ContainerRegistry::get(PatientsService::class);

    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');

    $_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);
    // The request forms show the result section only to a user who may enter results
    // or who is not at a collection site. Anyone else's post carries no results.
    if (!_isAllowed('/tb/results/tb-update-result.php') && ($_SESSION['accessType'] ?? null) === 'collection-site') {
        foreach (TbTestsService::REQUEST_FORM_RESULT_KEYS as $resultKey) {
            unset($_POST[$resultKey]);
        }
    }
    $tableName = "form_tb";
    // Acting as a LIS: the Testing Lab is this install's own lab, not a free
    // choice. The forms already constrain the dropdown, but AJAX endpoints
    // bypass ACL, so the rule only holds if it holds here. No-op on STS and
    // standalone installs, and on any LIS session with no resolved lab.
    $_POST['labId'] = $general->resolveRequestLabId($_POST['labId'] ?? null);
    // Same rule for the per-test cards on this form: every card runs at the
    // request's lab, unless that lab already has tests on this sample (referred in).
    // Blank cards are left blank so no phantom test is created.
    if (!empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId'])) {
        $_POST['testResult']['labId'] = $general->resolveTestCardLabIds($_POST['testResult']['labId'], $_POST['labId'] ?? null, 'tb_tests', 'tb_id', $_POST['tbSampleId'] ?? null);
    }

    $tableName1 = "activity_log";
    $testTableName = 'tb_tests';

    $general->assertFacilityAllowed((int) ($_POST['facilityId'] ?? 0));

    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($_POST['sampleCollectionDate'] ?? '', true);

    // The single-result forms post the received date; the per-test form posts it on
    // each card, and its latest test sets it below.
    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat(
        $_POST['sampleReceivedDate'] ?? $_POST['testResult']['sampleReceivedDate'][0] ?? null,
        true
    );
    $_POST['resultDispatchedDatetime'] = DateUtility::isoDateFormat($_POST['resultDispatchedDatetime'] ?? '', true);
    $_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($_POST['sampleTestedDateTime'] ?? '', true);
    $_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($_POST['sampleDispatchedDate'] ?? '', true);

    $_POST['resultDate'] = DateUtility::isoDateFormat($_POST['resultDate'] ?? '', true);

    $_POST['xpertDateOfResult'] = DateUtility::isoDateFormat($_POST['xpertDateOfResult'] ?? '', true);

    $_POST['tbLamDateOfResult'] = DateUtility::isoDateFormat($_POST['tbLamDateOfResult'] ?? '', true);

    $_POST['cultureDateOfResult'] = DateUtility::isoDateFormat($_POST['cultureDateOfResult'] ?? '', true);

    $_POST['identificationDateOfResult'] = DateUtility::isoDateFormat($_POST['identificationDateOfResult'] ?? '', true);

    $_POST['drugMGITDateOfResult'] = DateUtility::isoDateFormat($_POST['drugMGITDateOfResult'] ?? '', true);

    $_POST['drugLPADateOfResult'] = DateUtility::isoDateFormat($_POST['drugLPADateOfResult'] ?? '', true);

    $_POST['arrivalDateTime'] = DateUtility::isoDateFormat($_POST['arrivalDateTime'] ?? '', true);

    $_POST['requestedDate'] = DateUtility::isoDateFormat($_POST['requestedDate'] ?? '', true);

    if (in_array(trim((string) $_POST['sampleCode']), ['', '0'], true)) {
        $_POST['sampleCode'] = null;
    }

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

    if (!empty($_POST['dob'])) {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
    }

    if (!empty($_POST['firstSputumSamplesCollectionDate'])) {
        $_POST['firstSputumSamplesCollectionDate'] = DateUtility::isoDateFormat($_POST['firstSputumSamplesCollectionDate']);
    }

    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '', true);
    $_POST['approvedOn'] = DateUtility::isoDateFormat($_POST['approvedOn'] ?? '', true);

    if (isset($_POST['province']) && $_POST['province'] != "") {
        $province = explode("##", (string) $_POST['province']);
        $provinceId = $geolocationService->getProvinceIdByName($province[0]);
        $_POST['provinceId'] = empty($provinceId) ? $geolocationService->addGeoLocation($province[0]) : $provinceId;
    }

    if (isset($_POST['patientGender']) && (trim((string) $_POST['patientGender']) === 'male' || trim((string) $_POST['patientGender']) === 'unreported')) {
        $_POST['patientPregnant'] = "N/A";
        $_POST['breastfeeding'] = "N/A";
    }

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
    // Handle reason for TB test / purpose of test (consolidated into reason_for_tb_test column)
    if (!empty($_POST['purposeOfTbTest'])) {
        // Rwanda: simple string value
        $reasonForTest = json_encode($_POST['purposeOfTbTest']);
    } elseif (!empty($_POST['reasonForTbTest'])) {
        // Other countries: complex nested structure
        $reason = $_POST['reasonForTbTest'];
        $reason['reason'] = [$reason['reason'] => 'yes'];
        $reasonForTest = json_encode($reason);
    } else {
        $reasonForTest = null;
    }

    //Update patient Information in Patients Table
    //$patientsService->savePatient($_POST, 'form_tb');



    //$systemGeneratedCode = $patientsService->getSystemPatientId($_POST['patientId'], $_POST['patientGender'], DateUtility::isoDateFormat($_POST['dob'] ?? ''));


    if (isset($_POST['tbTestsRequested']) && is_array($_POST['tbTestsRequested'])) {
        $_POST['tbTestsRequested'] = json_encode($_POST['tbTestsRequested']);
    }
    // A result is kept only when the form says it is finalized. A form without that
    // question keeps the final interpretation it posted.
    if (
        trim((string) ($_POST['finalResult'] ?? '')) === ''
        || (array_key_exists('isResultFinalized', $_POST) && $_POST['isResultFinalized'] != 'yes')
    ) {
        $_POST['finalResult'] = null;
    }
    if (trim((string) ($_POST['finalResult'] ?? '')) !== '') {
        $resultSentToSource = 'pending';
    }
    // Rejection is a verdict on the sample, not on one test card. A TB sample is one
    // specimen transferred from lab to lab, so a rejected specimen is rejected for
    // every test on the request -- every form posts the one flat isSampleRejected,
    // and form_tb is the only place any reader looks for it.
    $sampleRejected = $_POST['isSampleRejected'] ?? null;
    if ($sampleRejected === 'yes') {
        $_POST['finalResult'] = null;
        $status = REJECTED;
        $resultSentToSource = 'pending';
    } elseif (trim((string) ($_POST['finalResult'] ?? '')) !== '') {
        $status = PENDING_APPROVAL; // Awaiting Approval, as the edit saves it
    }
    // form_tb.lab_id is the lab currently holding the sample, not the one that first
    // received it: save-tb-referral-helper.php moves it on every transfer, and
    // get-referral-samples.php reads it back to decide who may refer the sample next.
    // Rwanda's test cards are that chain of labs, so the last card is where the
    // sample is now -- the first card would drag a transferred sample back to the
    // lab it started at. Every other column folded from the cards below already
    // takes the last one.
    $labId = null;
    if (isset($_POST['labId']) && !empty($_POST['labId'])) {
        $labId = $_POST['labId'];
    } elseif (!empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId'])) {
        $cardLabIds = array_values(array_filter($_POST['testResult']['labId'], static fn($id) => !empty($id)));
        $labId = end($cardLabIds) ?: null;
    }
    if (!empty($_POST['riskFactors']) && is_array($_POST['riskFactors'])) {
        $_POST['riskFactors'] = json_encode($_POST['riskFactors']);
    }

    if (is_array($_POST['typeOfPatient']))
        $_POST['typeOfPatient']  = json_encode($_POST['typeOfPatient']);

    $tbData = [
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => $_POST['formId'],
        'facility_id' => empty($_POST['facilityId']) ? null : $_POST['facilityId'],
        'requesting_clinician' => empty($_POST['requestingClinician']) ? null : $_POST['requestingClinician'],
        'specimen_quality' => empty($_POST['testNumber']) ? null : $_POST['testNumber'],
        'province_id' => empty($_POST['provinceId']) ? null : $_POST['provinceId'],
        'lab_id' => $labId,
        'affiliated_lab_id' => empty($_POST['affiliatedLabId']) ? null : $_POST['affiliatedLabId'],
        //'affiliated_district_hospital' => empty($_POST['affiliatedDistrictHospital']) ? null : $_POST['affiliatedDistrictHospital'],
        'etb_tracker_number' => empty($_POST['trackerNo']) ? null : $_POST['trackerNo'],
        //'system_patient_code' => $systemGeneratedCode,
        'implementing_partner' => empty($_POST['implementingPartner']) ? null : $_POST['implementingPartner'],
        'funding_source' => empty($_POST['fundingSource']) ? null : $_POST['fundingSource'],
        'referring_unit' => empty($_POST['referringUnit']) ? null : $_POST['referringUnit'],
        'patient_id' => empty($_POST['patientId']) ? null : $_POST['patientId'],
        'patient_type' => empty($_POST['typeOfPatient']) ? null : $_POST['typeOfPatient'],
        'patient_name' => empty($_POST['firstName']) ? null : $_POST['firstName'],
        'patient_surname' => empty($_POST['lastName']) ? null : $_POST['lastName'],
        'patient_dob' => empty($_POST['dob']) ? null : $_POST['dob'],
        'patient_gender' => empty($_POST['patientGender']) ? null : $_POST['patientGender'],
        'is_patient_pregnant' => $_POST['patientPregnant'] ?? null,
        'is_patient_breastfeeding' => $_POST['breastfeeding'] ?? null,
        'patient_age' => empty($_POST['patientAge']) ? null : $_POST['patientAge'],
        'patient_weight' => empty($_POST['patientWeight']) ? null : $_POST['patientWeight'],
        'patient_phone' => empty($_POST['patientPhoneNumber']) ? null : $_POST['patientPhoneNumber'],
        'patient_address' => empty($_POST['patientAddress']) ? null : $_POST['patientAddress'],
        'is_displaced_population' => empty($_POST['displacedPopulation']) ? null : $_POST['displacedPopulation'],
        'is_referred_by_community_actor' => empty($_POST['isReferredByCommunityActor']) ? null : $_POST['isReferredByCommunityActor'],
        'reason_for_tb_test' => $reasonForTest,
        'risk_factors' => empty($_POST['riskFactors']) ? null : $_POST['riskFactors'],
        'risk_factor_other' => empty($_POST['riskFactorsOther']) ? null : $_POST['riskFactorsOther'],
        'recommended_corrective_action' => $_POST['correctiveAction'] ?? null,
        //'purpose_of_test' is now consolidated into 'reason_for_tb_test'
        'hiv_status' => empty($_POST['hivStatus']) ? null : $_POST['hivStatus'],
        'is_patient_initiated_on_tb_treatment' => empty($_POST['isPatientInitiatedTreatment']) ? null : $_POST['isPatientInitiatedTreatment'],
        'date_of_treatment_initiation' => empty($_POST['treatmentDate']) ? null : DateUtility::isoDateFormat($_POST['treatmentDate']),
        'current_regimen' => empty($_POST['currentRegimen']) ? null : $_POST['currentRegimen'],
        'date_of_initiation_of_current_regimen' => empty($_POST['regimenDate']) ? null : DateUtility::isoDateFormat($_POST['regimenDate']),
        'previously_treated_for_tb' => empty($_POST['previouslyTreatedForTB']) ? null : $_POST['previouslyTreatedForTB'],
        'tests_requested' => empty($_POST['tbTestsRequested']) ? null : $_POST['tbTestsRequested'],
        'number_of_sputum_samples' => empty($_POST['numberOfSputumSamples']) ? null : $_POST['numberOfSputumSamples'],
        'first_sputum_samples_collection_date' => empty($_POST['firstSputumSamplesCollectionDate']) ? null : $_POST['firstSputumSamplesCollectionDate'],
        'sample_requestor_name' => empty($_POST['sampleRequestorName']) ? null : $_POST['sampleRequestorName'],
        'specimen_type' => empty($_POST['specimenType']) ? null : $_POST['specimenType'],
        'sample_collection_date' => empty($_POST['sampleCollectionDate']) ? null : $_POST['sampleCollectionDate'],
        'sample_dispatched_datetime' => empty($_POST['sampleDispatchedDate']) ? null : $_POST['sampleDispatchedDate'],
        'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
        'is_specimen_reordered' => empty($_POST['reOrderedCorrectiveAction']) ? null : $_POST['reOrderedCorrectiveAction'],
        'is_sample_rejected' => $sampleRejected,
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
        'tblam_result_date' => empty($_POST['tbLamDateOfResult']) ? null : $_POST['tbLamDateOfResult'],
        'identification_result_date' => empty($_POST['identificationDateOfResult']) ? null : $_POST['identificationDateOfResult'],
        'drug_mgit_result_date' => empty($_POST['drugMGITDateOfResult']) ? null : $_POST['drugMGITDateOfResult'],
        'drug_lpa_result_date' => empty($_POST['drugLPADateOfResult']) ? null : $_POST['drugLPADateOfResult'],
        'result_sent_to_source' => $resultSentToSource,
        'result_dispatched_datetime' => empty($_POST['resultDispatchedDatetime']) ? null : $_POST['resultDispatchedDatetime'],
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime' => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'other_referring_unit' => (isset($_POST['typeOfReferringUnit']) && $_POST['typeOfReferringUnit'] != "") ? $_POST['typeOfReferringUnit'] : null,
        'other_specimen_type' => (isset($_POST['specimenTypeOther']) && $_POST['specimenTypeOther'] != "") ? $_POST['specimenTypeOther'] : null,
        'other_patient_type' => (isset($_POST['typeOfPatientOther']) && $_POST['typeOfPatientOther'] != "") ? $_POST['typeOfPatientOther'] : null,
        'tested_by' => empty($_POST['testedBy']) ? null : $_POST['testedBy'],
        'result_date' => empty($_POST['resultDate']) ? null : $_POST['resultDate'],
        'rejection_on' => (!empty($_POST['rejectionDate']) && $sampleRejected === 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status' => $status,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $sampleRejected === 'yes') ? $_POST['sampleRejectionReason'] : 'N/A',
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => !empty($_POST['requestedDate']) ? $_POST['requestedDate'] : DateUtility::getCurrentDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'result_modified' => 'no',
        'lab_tech_comments' => empty($_POST['labComments']) ? '' : $_POST['labComments'],
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId'],
    ];

    if ($general->isLISInstance() || $general->isStandaloneInstance()) {
        $tbData['source_of_request'] = 'vlsm';
    } elseif ($general->isSTSInstance()) {
        $tbData['source_of_request'] = 'vlsts';
    }


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
     * that posts no testResult at all -- the per-test form hides its cards on an
     * STS and from users who cannot enter results -- leaves `tb_tests` alone.
     */
    $hasMultipleTests = !empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId']);

    // The tests and the sample are one change.
    $db->beginTransaction();
    if (!empty($_POST['tbSampleId']) && $hasMultipleTests) {
        // A new sample's cards are all its tests: a form posted twice replaces the
        // tests the first post added rather than adding them again.
        $db->where('tb_id', $_POST['tbSampleId']);
        $db->delete($testTableName);
        /** @var TbTestsService $tbTests */
        $tbTests = ContainerRegistry::get(TbTestsService::class);
        $tbTests->saveCards(
            (int) $_POST['tbSampleId'],
            (array) ($_POST['testResult'] ?? []),
            (array) ($_POST['deletedTestIds'] ?? []),
            $_SESSION['userId'] ?? null
        );
        // The latest test's details come from the tests, whatever the form posted
        // at the sample level.
        $latestTestColumns = $tbTests->latestTestColumns((int) $_POST['tbSampleId']);
        $tbData = array_merge($tbData, $latestTestColumns);
        // Neither the latest test nor the form gives a received date: the sample keeps its own.
        if (!isset($latestTestColumns['sample_received_at_lab_datetime'])
            && empty($tbData['sample_received_at_lab_datetime'])) {
            unset($tbData['sample_received_at_lab_datetime']);
        }
    } elseif (!empty($_POST['tbSampleId']) && isset($_POST['testResult']) && is_array($_POST['testResult'])) {
        ContainerRegistry::get(TbTestsService::class)->saveMicroscopyRows(
            (int) $_POST['tbSampleId'],
            $_POST['testResult'],
            (array) ($_POST['actualNo'] ?? []),
            $labId
        );
    }

    $tbData['is_encrypted'] = 'no';
    if (isset($_POST['encryptPII']) && $_POST['encryptPII'] == 'yes') {
        $key = (string) $general->getGlobalConfig('key');
        $encryptedPatientId = $general->crypto('encrypt', $tbData['patient_id'], $key);
        $encryptedPatientName = $general->crypto('encrypt', $tbData['patient_name'], $key);
        $encryptedPatientSurName = $general->crypto('encrypt', $tbData['patient_surname'], $key);

        $tbData['patient_id'] = $encryptedPatientId;
        $tbData['patient_name'] = $encryptedPatientName;
        $tbData['patient_surname'] = $encryptedPatientSurName;
        $tbData['is_encrypted'] = 'yes';
    }

    $id = false;
    if (!empty($_POST['tbSampleId'])) {
        $db->where('tb_id', $_POST['tbSampleId']);
        $id = $db->update($tableName, $tbData);
    }
    // The tests are kept only with the sample they belong to.
    $id === true ? $db->commitTransaction() : $db->rollbackTransaction();
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("TB test request added successfully");
        //Add event log
        $eventType = 'tb-add-request';
        $action = $_SESSION['userName'] . ' added a new TB request with the Sample ID/Code  ' . $_POST['tbSampleId'];
        $resource = 'tb-add-request';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = _translate("Unable to add this TB sample. Please try again later");
    }

    if (!empty($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
        header("Location:/tb/requests/tb-add-request.php");
    } else {
        header("Location:/tb/requests/tb-requests.php");
    }
} catch (Throwable $e) {
    $db->rollbackTransaction();
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    // Don't leave the user staring at a blank page: surface a message and send
    // them back to the form instead of dying silently after logging.
    $_SESSION['alertMsg'] = _translate("Unable to add this TB sample. Please try again later");
    header("Location:/tb/requests/tb-add-request.php");
}
