<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Services\GeoLocationsService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);


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
    if (isset($_POST['sampleDispatchedDate']) && trim((string) $_POST['sampleDispatchedDate']) != "") {
        $sampleDispatchedDate = explode(" ", (string) $_POST['sampleDispatchedDate']);
        $_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
    } else {
        $_POST['sampleDispatchedDate'] = null;
    }

    if (isset($_POST['resultDate']) && trim((string) $_POST['resultDate']) != "") {
        $resultDate = explode(" ", (string) $_POST['resultDate']);
        $_POST['resultDate'] = DateUtility::isoDateFormat($resultDate[0]) . " " . $resultDate[1];
    } else {
        $_POST['resultDate'] = null;
    }
    //echo '<pre>'; print_r($_POST); die;
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

    if (!empty($_POST['firstSputumSamplesCollectionDate'])) {
        $_POST['firstSputumSamplesCollectionDate'] = DateUtility::isoDateFormat($_POST['firstSputumSamplesCollectionDate']);
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
        $provinceId = $geolocationService->getProvinceIdByName($province[0]);
        if (empty($provinceId)) {
            $_POST['provinceId'] = $geolocation->addGeoLocation($province[0]);
        } else {
            $_POST['provinceId'] = $provinceId;
        }
    }

    if (isset($_POST['patientGender']) && (trim((string) $_POST['patientGender']) == 'male' || trim((string) $_POST['patientGender']) == 'unreported')) {
        $_POST['patientPregnant'] = "N/A";
        $_POST['breastfeeding'] = "N/A";
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
    $reason = $_POST['reasonForTbTest'];
    $reason['reason'] = array($reason['reason'] => 'yes');

    //Update patient Information in Patients Table
    $patientsService->savePatient($_POST, 'form_tb');



    $systemGeneratedCode = $patientsService->getSystemPatientId($_POST['patientId'], $_POST['patientGender'], DateUtility::isoDateFormat($_POST['dob'] ?? ''));

    $tbData = array(
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => $_POST['formId'],
        'facility_id' => !empty($_POST['facilityId']) ? $_POST['facilityId'] : null,
        'requesting_clinician' => !empty($_POST['requestingClinician']) ? $_POST['requestingClinician'] : null,
        'specimen_quality' => !empty($_POST['testNumber']) ? $_POST['testNumber'] : null,
        'province_id' => !empty($_POST['provinceId']) ? $_POST['provinceId'] : null,
        'lab_id' => !empty($_POST['labId']) ? $_POST['labId'] : null,
        'system_patient_code' => $systemGeneratedCode,
        'implementing_partner' => !empty($_POST['implementingPartner']) ? $_POST['implementingPartner'] : null,
        'funding_source' => !empty($_POST['fundingSource']) ? $_POST['fundingSource'] : null,
        'referring_unit' => !empty($_POST['referringUnit']) ? $_POST['referringUnit'] : null,
        'patient_id' => !empty($_POST['patientId']) ? $_POST['patientId'] : null,
        'patient_type' => !empty($_POST['typeOfPatient']) ? json_encode($_POST['typeOfPatient']) : null,
        'patient_name' => !empty($_POST['firstName']) ? $_POST['firstName'] : null,
        'patient_surname' => !empty($_POST['lastName']) ? $_POST['lastName'] : null,
        'patient_dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
        'patient_gender' => !empty($_POST['patientGender']) ? $_POST['patientGender'] : null,
        'is_patient_pregnant' => $_POST['patientPregnant'] ?? null,
        'is_patient_breastfeeding' => $_POST['breastfeeding'] ?? null,
        'patient_age' => !empty($_POST['patientAge']) ? $_POST['patientAge'] : null,
        'patient_weight' => !empty($_POST['patientWeight']) ? $_POST['patientWeight'] : null,
        'patient_phone' => !empty($_POST['patientPhoneNumber']) ? $_POST['patientPhoneNumber'] : null,
        'patient_address' => !empty($_POST['patientAddress']) ? $_POST['patientAddress'] : null,
        'is_displaced_population' => !empty($_POST['displacedPopulation']) ? $_POST['displacedPopulation'] : null,
        'is_referred_by_community_actor' => !empty($_POST['isReferredByCommunityActor']) ? $_POST['isReferredByCommunityActor'] : null,
        'reason_for_tb_test' => !empty($reason) ? json_encode($reason) : null,
        'hiv_status' => !empty($_POST['hivStatus']) ? $_POST['hivStatus'] : null,
        'previously_treated_for_tb' => !empty($_POST['previouslyTreatedForTB']) ? $_POST['previouslyTreatedForTB'] : null,
        'tests_requested' => !empty($_POST['testTypeRequested']) ? json_encode($_POST['testTypeRequested']) : null,
        'number_of_sputum_samples' => !empty($_POST['numberOfSputumSamples']) ? $_POST['numberOfSputumSamples'] : null,
        'first_sputum_samples_collection_date' => !empty($_POST['firstSputumSamplesCollectionDate']) ? $_POST['firstSputumSamplesCollectionDate'] : null,
        'sample_requestor_name' => !empty($_POST['sampleRequestorName']) ? $_POST['sampleRequestorName'] : null,
        'specimen_type' => !empty($_POST['specimenType']) ? $_POST['specimenType'] : null,
        'sample_collection_date' => !empty($_POST['sampleCollectionDate']) ? $_POST['sampleCollectionDate'] : null,
        'sample_dispatched_datetime' => !empty($_POST['sampleDispatchedDate']) ? $_POST['sampleDispatchedDate'] : null,
        'sample_received_at_lab_datetime' => !empty($_POST['sampleReceivedDate']) ? $_POST['sampleReceivedDate'] : null,
        'is_sample_rejected' => !empty($_POST['isSampleRejected']) ? $_POST['isSampleRejected'] : '',
        'recommended_corrective_action' => !empty($_POST['correctiveAction']) ? $_POST['correctiveAction'] : '',
        'result' => !empty($_POST['result']) ? $_POST['result'] : $_POST['xPertMTMResult'],
        'xpert_mtb_result' => !empty($_POST['xPertMTMResult']) ? $_POST['xPertMTMResult'] : null,
        'result_sent_to_source' => $resultSentToSource,
        'result_dispatched_datetime' => !empty($_POST['resultDispatchedDatetime']) ? $_POST['resultDispatchedDatetime'] : null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != "") ? $_POST['approvedBy'] : "",
        'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != "") ? $_POST['approvedOn'] : null,
        'sample_tested_datetime' => (isset($_POST['sampleTestedDateTime']) && $_POST['sampleTestedDateTime'] != "") ? $_POST['sampleTestedDateTime'] : null,
        'other_referring_unit' => (isset($_POST['typeOfReferringUnit']) && $_POST['typeOfReferringUnit'] != "") ? $_POST['typeOfReferringUnit'] : null,
        'other_specimen_type' => (isset($_POST['specimenTypeOther']) && $_POST['specimenTypeOther'] != "") ? $_POST['specimenTypeOther'] : null,
        'other_patient_type' => (isset($_POST['typeOfPatientOther']) && $_POST['typeOfPatientOther'] != "") ? $_POST['typeOfPatientOther'] : null,
        'tested_by' => !empty($_POST['testedBy']) ? $_POST['testedBy'] : null,
        'result_date' => !empty($_POST['resultDate']) ? $_POST['resultDate'] : null,
        'rejection_on' => (!empty($_POST['rejectionDate']) && $_POST['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
        'result_status' => $status,
        'data_sync' => 0,
        'reason_for_sample_rejection' => (isset($_POST['sampleRejectionReason']) && $_POST['isSampleRejected'] == 'yes') ? $_POST['sampleRejectionReason'] : null,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => (isset($_POST['requestedDate']) && $_POST['requestedDate'] == 'yes') ? $_POST['requestedDate'] : DateUtility::getCurrentDateTime(),
        'sample_registered_at_lab' => DateUtility::getCurrentDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'result_modified'  => 'no',
        'lab_tech_comments' => !empty($_POST['labComments']) ? $_POST['labComments'] : '',
        'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] : $_SESSION['userId']
    );

    if ($general->isLISInstance() || $general->isStandaloneInstance()) {
        $tbData['source_of_request'] = 'vlsm';
    } elseif ($general->isSTSInstance()) {
        $tbData['source_of_request'] = 'vlsts';
    }

    if (isset($_POST['tbSampleId']) && $_POST['tbSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
        if (!empty($_POST['testResult'])) {
            foreach ($_POST['testResult'] as $testKey => $testResult) {
                if (!empty($testResult)) {
                    $db->insert(
                        $testTableName,
                        array(
                            'tb_id' => $_POST['tbSampleId'],
                            'test_result' => $testResult,
                            'actual_no' => $_POST['actualNo'][$testKey] ?? null,
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
        error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
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
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
