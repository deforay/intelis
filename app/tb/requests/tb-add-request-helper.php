<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
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

    $tableName = "form_tb";
    $tableName1 = "activity_log";
    $testTableName = 'tb_tests';


    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($_POST['sampleCollectionDate'] ?? '', true);

    //Set sample received date
    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($_POST['testResult']['sampleReceivedDate'][0] ?? null, true);
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

    //echo '<pre>'; print_r($_POST); die;
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

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $_POST['finalResult'] = null;
        $status = REJECTED;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['dob'])) {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
    }

    if (!empty($_POST['firstSputumSamplesCollectionDate'])) {
        $_POST['firstSputumSamplesCollectionDate'] = DateUtility::isoDateFormat($_POST['firstSputumSamplesCollectionDate']);
    }

    if (!empty($_POST['finalResult'])) {
        $resultSentToSource = 'pending';
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
    $reason = $_POST['reasonForTbTest'] ?? null;
    $reason['reason'] = [$reason['reason'] => 'yes'];

    //Update patient Information in Patients Table
    //$patientsService->savePatient($_POST, 'form_tb');



    //$systemGeneratedCode = $patientsService->getSystemPatientId($_POST['patientId'], $_POST['patientGender'], DateUtility::isoDateFormat($_POST['dob'] ?? ''));
    if (!empty($_POST['purposeOfTbTest']) && is_array($_POST['purposeOfTbTest'])) {
        $_POST['purposeOfTbTest'] = implode(",", $_POST['purposeOfTbTest']);
    }
    $tbData = [
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => $_POST['formId'],
        'facility_id' => empty($_POST['facilityId']) ? null : $_POST['facilityId'],
        'requesting_clinician' => empty($_POST['requestingClinician']) ? null : $_POST['requestingClinician'],
        'specimen_quality' => empty($_POST['testNumber']) ? null : $_POST['testNumber'],
        'province_id' => empty($_POST['provinceId']) ? null : $_POST['provinceId'],
        'lab_id' => !empty($_POST['labId']) ? $_POST['labId'] : ($_POST['testResult']['labId'][0] ?? null),
        'affiliated_lab_id' => empty($_POST['affiliatedLabId']) ? null : $_POST['affiliatedLabId'],
        'affiliated_district_hospital' => empty($_POST['affiliatedDistrictHospital']) ? null : $_POST['affiliatedDistrictHospital'],
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
        'reason_for_tb_test' => empty($reason) ? null : json_encode($reason),
        'risk_factors' => empty($_POST['riskFactors']) ? null : $_POST['riskFactors'],
        'risk_factor_other' => empty($_POST['riskFactorsOther']) ? null : $_POST['riskFactorsOther'],
        'recommended_corrective_action' => $_POST['correctiveAction'] ?? null,
        'purpose_of_test' => empty($_POST['purposeOfTbTest']) ? null : $_POST['purposeOfTbTest'],
        'hiv_status' => empty($_POST['hivStatus']) ? null : $_POST['hivStatus'],
        'is_patient_initiated_on_tb_treatment' => empty($_POST['isPatientInitiatedTreatment']) ? null : $_POST['isPatientInitiatedTreatment'],
        'date_of_treatment_initiation' => empty($_POST['treatmentDate']) ? null : DateUtility::isoDateFormat($_POST['treatmentDate']),
        'current_regimen' => empty($_POST['currentRegimen']) ? null : $_POST['currentRegimen'],
        'date_of_initiation_of_current_regimen' => empty($_POST['regimenDate']) ? null : DateUtility::isoDateFormat($_POST['regimenDate']),
        'previously_treated_for_tb' => empty($_POST['previouslyTreatedForTB']) ? null : $_POST['previouslyTreatedForTB'],
        'tests_requested' => empty($data['tbTestsRequested']) ? null : json_encode($data['tbTestsRequested']),
        'number_of_sputum_samples' => empty($_POST['numberOfSputumSamples']) ? null : $_POST['numberOfSputumSamples'],
        'first_sputum_samples_collection_date' => empty($_POST['firstSputumSamplesCollectionDate']) ? null : $_POST['firstSputumSamplesCollectionDate'],
        'sample_requestor_name' => empty($_POST['sampleRequestorName']) ? null : $_POST['sampleRequestorName'],
        'specimen_type' => empty($_POST['specimenType']) ? null : $_POST['specimenType'],
        'sample_collection_date' => empty($_POST['sampleCollectionDate']) ? null : $_POST['sampleCollectionDate'],
        'sample_dispatched_datetime' => empty($_POST['sampleDispatchedDate']) ? null : $_POST['sampleDispatchedDate'],
        'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
        'is_specimen_reordered' => empty($_POST['reOrderedCorrectiveAction']) ? null : $_POST['reOrderedCorrectiveAction'],
        'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
        'result' => $_POST['finalResult'] ?? null,
        'tb_lam_result' => $_POST['tbLamResult'] ?? null,
        'xpert_mtb_result' => empty($_POST['xPertMTMResult']) ? null : $_POST['xPertMTMResult'],
        'culture_result' => empty($_POST['cultureResult']) ? null : $_POST['cultureResult'],
        'identification_result' => empty($_POST['identicationResult']) ? null : $_POST['identicationResult'],
        'drug_mgit_result' => empty($_POST['drugMGITResult']) ? null : $_POST['drugMGITResult'],
        'drug_lpa_result' => empty($_POST['drugLPAResult']) ? null : $_POST['drugLPAResult'],
        'xpert_result_date' => empty($_POST['xPertDateOfResult']) ? null : $_POST['xPertDateOfResult'],
        'culture_result_date' => empty($_POST['cultureDateOfResult']) ? null : $_POST['cultureDateOfResult'],
        'tblam_result_date' => empty($_POST['tbLamDateOfResult']) ? null : $_POST['tbLamDateOfResult'],
        'identification_result_date' => empty($_POST['identicationDateOfResult']) ? null : $_POST['identicationDateOfResult'],
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
        'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status' => $status,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : 'N/A',
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => (isset($_POST['requestedDate']) && $_POST['requestedDate'] == 'yes') ? $_POST['requestedDate'] : DateUtility::getCurrentDateTime(),
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

    /* echo "<pre>";
    print_r($tbData);
    die; */
    /**
     * TB Test Data Handling Logic:
     *
     * This system supports two types of TB forms:
     *
     * 1. MULTIPLE TESTS PER SAMPLE (e.g., Rwanda):
     *    - Form sends nested array: testResult[fieldName][]
     *    - Each test has its own lab, specimen type, reviewer, approver, etc.
     *    - Tests are stored in `tb_tests` table (one row per test)
     *    - The LATEST test's data is also stored in `form_tb` for quick access
     *
     * 2. SINGLE TEST PER SAMPLE (e.g., Sierra Leone, South Sudan, Burkina Faso):
     *    - Form sends flat array: testResult[] (just result values)
     *    - Test-level fields (reviewer, approver, etc.) are direct POST fields
     *    - All data goes directly to `form_tb` table
     *    - `tb_tests` table is NOT used
     *
     * Detection: If testResult[labId][] exists as array = multiple tests
     */
    $hasMultipleTests = !empty($_POST['testResult']['labId']) && is_array($_POST['testResult']['labId']);

    if ($hasMultipleTests) {
        // Delete existing tests (for edit/re-add scenarios)
        if (!empty($_POST['tbSampleId'])) {
            $db->where('tb_id', $_POST['tbSampleId']);
            $db->delete($testTableName);
        }

        // Insert all tests into tb_tests
        $testResult = $_POST['testResult'];
        foreach ($testResult['labId'] as $key => $labid) {
            if (!empty($labid)) {
                $db->insert($testTableName, [
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
                    'updated_datetime' => DateUtility::getCurrentDateTime()
                ]);
            }
        }

        // Update $tbData with LATEST test's data for form_tb
        $lastIndex = count($testResult['labId']) - 1;
        $tbData['lab_id'] = $testResult['labId'][$lastIndex] ?? null;
        $tbData['sample_received_at_lab_datetime'] = DateUtility::isoDateFormat($testResult['sampleReceivedDate'][$lastIndex] ?? null, true);
        $tbData['is_sample_rejected'] = $testResult['isSampleRejected'][$lastIndex] ?? null;
        $tbData['reason_for_sample_rejection'] = $testResult['sampleRejectionReason'][$lastIndex] ?? null;
        $tbData['rejection_on'] = DateUtility::isoDateFormat($testResult['rejectionDate'][$lastIndex] ?? null);
        $tbData['sample_tested_datetime'] = DateUtility::isoDateFormat($testResult['sampleTestedDateTime'][$lastIndex] ?? null, true);
        $tbData['tested_by'] = $testResult['testedBy'][$lastIndex] ?? null;
        $tbData['result_reviewed_by'] = $testResult['reviewedBy'][$lastIndex] ?? null;
        $tbData['result_reviewed_datetime'] = DateUtility::isoDateFormat($testResult['reviewedOn'][$lastIndex] ?? null, true);
        $tbData['result_approved_by'] = $testResult['approvedBy'][$lastIndex] ?? null;
        $tbData['result_approved_datetime'] = DateUtility::isoDateFormat($testResult['approvedOn'][$lastIndex] ?? null, true);
    }
    // For flat testResult[] (other countries): no tb_tests operations, form_tb already has all data from direct POST fields

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
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
}
