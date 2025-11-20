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

try {
    $instanceId = '';
    if (!empty($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    if (empty($instanceId) && $_POST['instanceId']) {
        $instanceId = $_POST['instanceId'];
    }
    if (!empty($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) !== "") {
        $sampleCollectionDate = explode(" ", (string) $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }

    //Set sample received date
    $_POST['sampleReceivedDate'] ??= $_POST['testResult']['sampleReceivedDate'][0];

    if (!empty($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== "") {
        $sampleReceivedDate = explode(" ", (string) $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    if (!empty($_POST['resultDispatchedDatetime']) && trim((string) $_POST['resultDispatchedDatetime']) !== "") {
        $resultDispatchedDatetime = explode(" ", (string) $_POST['resultDispatchedDatetime']);
        $_POST['resultDispatchedDatetime'] = DateUtility::isoDateFormat($resultDispatchedDatetime[0]) . " " . $resultDispatchedDatetime[1];
    } else {
        $_POST['resultDispatchedDatetime'] = null;
    }
    if (!empty($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) !== "") {
        $sampleTestedDate = explode(" ", (string) $_POST['sampleTestedDateTime']);
        $_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestedDateTime'] = null;
    }
    if (isset($_POST['sampleDispatchedDate']) && trim((string) $_POST['sampleDispatchedDate']) !== "") {
        $sampleDispatchedDate = explode(" ", (string) $_POST['sampleDispatchedDate']);
        $_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
    } else {
        $_POST['sampleDispatchedDate'] = null;
    }

    if (isset($_POST['resultDate']) && trim((string) $_POST['resultDate']) !== "") {
        $resultDate = explode(" ", (string) $_POST['resultDate']);
        $_POST['resultDate'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['resultDate'] = null;
    }

    if (isset($_POST['xpertDateOfResult']) && trim((string) $_POST['xpertDateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['xpertDateOfResult']);
        $_POST['xpertDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['xpertDateOfResult'] = null;
    }

    if (isset($_POST['tbLamDateOfResult']) && trim((string) $_POST['tbLamDateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['tbLamDateOfResult']);
        $_POST['tbLamDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['tbLamDateOfResult'] = null;
    }

    if (isset($_POST['cultureDateOfResult']) && trim((string) $_POST['cultureDateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['cultureDateOfResult']);
        $_POST['cultureDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['cultureDateOfResult'] = null;
    }

    if (isset($_POST['identificationDateOfResult']) && trim((string) $_POST['identificationDateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['identificationDateOfResult']);
        $_POST['identificationDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['identificationDateOfResult'] = null;
    }

    if (isset($_POST['drugMGITDateOfResult']) && trim((string) $_POST['drugMGITDateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['drugMGITDateOfResult']);
        $_POST['drugMGITDateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['drugMGITDateOfResult'] = null;
    }

    if (isset($_POST['drugLPADateOfResult']) && trim((string) $_POST['drugLPADateOfResult']) !== "") {
        $resultDate = explode(" ", (string) $_POST['drugLPADateOfResult']);
        $_POST['drugLPADateOfResult'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['drugLPADateOfResult'] = null;
    }

    //echo '<pre>'; print_r($_POST); die;
    if (!empty($_POST['arrivalDateTime']) && trim((string) $_POST['arrivalDateTime']) !== "") {
        $arrivalDate = explode(" ", (string) $_POST['arrivalDateTime']);
        $_POST['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['arrivalDateTime'] = null;
    }

    if (!empty($_POST['requestedDate']) && trim((string) $_POST['requestedDate']) !== "") {
        $arrivalDate = explode(" ", (string) $_POST['requestedDate']);
        $_POST['requestedDate'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
    } else {
        $_POST['requestedDate'] = null;
    }

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
        $_POST['result'] = null;
        $status = REJECTED;
        $resultSentToSource = 'pending';
    }
    if (!empty($_POST['dob'])) {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
    }

    if (!empty($_POST['firstSputumSamplesCollectionDate'])) {
        $_POST['firstSputumSamplesCollectionDate'] = DateUtility::isoDateFormat($_POST['firstSputumSamplesCollectionDate']);
    }

    if (!empty($_POST['result'])) {
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

    $tbData = [
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => $_POST['formId'],
        'facility_id' => empty($_POST['facilityId']) ? null : $_POST['facilityId'],
        'requesting_clinician' => empty($_POST['requestingClinician']) ? null : $_POST['requestingClinician'],
        'specimen_quality' => empty($_POST['testNumber']) ? null : $_POST['testNumber'],
        'province_id' => empty($_POST['provinceId']) ? null : $_POST['provinceId'],
        'lab_id' => empty($_POST['labId']) ? $_POST['testResult']['labId'][0] : $_POST['labId'],
        'affiliated_lab_id' => empty($_POST['affiliatedLabId']) ? null : $_POST['affiliatedLabId'],
        'etb_tracker_number' => empty($_POST['trackerNo']) ? null : $_POST['trackerNo'],
        //'system_patient_code' => $systemGeneratedCode,
        'implementing_partner' => empty($_POST['implementingPartner']) ? null : $_POST['implementingPartner'],
        'funding_source' => empty($_POST['fundingSource']) ? null : $_POST['fundingSource'],
        'referring_unit' => empty($_POST['referringUnit']) ? null : $_POST['referringUnit'],
        'patient_id' => empty($_POST['patientId']) ? null : $_POST['patientId'],
        'patient_type' => empty($_POST['typeOfPatient']) ? null : json_encode($_POST['typeOfPatient']),
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
        'recommended_corrective_action' => empty($_POST['correctiveAction']) ? null : $_POST['correctiveAction'],
        'purpose_of_test' => empty($_POST['purposeOfTbTest']) ? null : $_POST['purposeOfTbTest'],
        'hiv_status' => empty($_POST['hivStatus']) ? null : $_POST['hivStatus'],
        'is_patient_initiated_on_tb_treatment' => empty($_POST['isPatientInitiatedTreatment']) ? null : $_POST['isPatientInitiatedTreatment'],
        'date_of_treatment_initiation' => empty($_POST['treatmentDate']) ? null : DateUtility::isoDateFormat($_POST['treatmentDate']),
        'current_regimen' => empty($_POST['currentRegimen']) ? null : $_POST['currentRegimen'],
        'date_of_initiation_of_current_regimen' => empty($_POST['regimenDate']) ? null : DateUtility::isoDateFormat($_POST['regimenDate']),
        'previously_treated_for_tb' => empty($_POST['previouslyTreatedForTB']) ? null : $_POST['previouslyTreatedForTB'],
        'tests_requested' => empty($_POST['testTypeRequested']) ? null : json_encode($_POST['testTypeRequested']),
        'number_of_sputum_samples' => empty($_POST['numberOfSputumSamples']) ? null : $_POST['numberOfSputumSamples'],
        'first_sputum_samples_collection_date' => empty($_POST['firstSputumSamplesCollectionDate']) ? null : $_POST['firstSputumSamplesCollectionDate'],
        'sample_requestor_name' => empty($_POST['sampleRequestorName']) ? null : $_POST['sampleRequestorName'],
        'specimen_type' => empty($_POST['specimenType']) ? null : $_POST['specimenType'],
        'sample_collection_date' => empty($_POST['sampleCollectionDate']) ? null : $_POST['sampleCollectionDate'],
        'sample_dispatched_datetime' => empty($_POST['sampleDispatchedDate']) ? null : $_POST['sampleDispatchedDate'],
        'sample_received_at_lab_datetime' => empty($_POST['sampleReceivedDate']) ? null : $_POST['sampleReceivedDate'],
        'is_sample_rejected' => (isset($_POST['isSampleRejected']) && !empty($_POST['isSampleRejected'])) ? $_POST['isSampleRejected'] : 'no',
        'recommended_corrective_action' => empty($_POST['correctiveAction']) ? '' : $_POST['correctiveAction'],
        'result' => empty($_POST['result']) ? $_POST['xPertMTMResult'] ?? $_POST['finalResult'] : $_POST['result'],
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
    if ($_POST['formId'] == 7) {
        if (!empty($_POST['testResult'])) {
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
                            'tested_by' => $testResult['testedBy'][$key] ?? null,
                            'result_reviewed_by' => $testResult['reviewedBy'][$key] ?? null,
                            'result_reviewed_datetime' => DateUtility::isoDateFormat($testResult['reviewedOn'][$key] ?? null, true),
                            'result_approved_by' => $testResult['approvedBy'][$key] ?? null,
                            'result_approved_datetime' => DateUtility::isoDateFormat($testResult['approvedOn'][$key] ?? null, true),
                            'revised_by' => $testResult['revisedBy'][$key] ?? null,
                            'revised_on' => DateUtility::isoDateFormat($testResult['revisedOn'][$key] ?? null, true),
                            'comments' => $testResult['comments'][$key] ?? null,
                            'updated_datetime' => DateUtility::getCurrentDateTime()
                        ]
                    );
                }
            }
        }
    } elseif (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (!empty($_POST['testResult'])) {
            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (!empty($testResult)) {
                    $db->insert(
                        $testTableName,
                        ['tb_id' => $_POST['tbSampleId'], 'test_result' => $testResult, 'actual_no' => $_POST['actualNo'][$testKey] ?? null, 'updated_datetime' => DateUtility::getCurrentDateTime()]
                    );
                }
            }
        }
    } else {
        $db->where('tb_id', $_POST['tbSampleId']);
        $db->delete($testTableName);
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
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
