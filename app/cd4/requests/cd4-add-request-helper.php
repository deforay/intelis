<?php

use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use App\Services\CD4Service;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Exceptions\SystemException;
use App\Utilities\ValidationUtility;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Cd4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);


$formId = (int) $general->getGlobalConfig('vl_form');

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');

$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);


$instanceId = $general->getInstanceId();

try {

    $db->beginTransaction();

    $mandatoryFields = [
        $_POST['cd4SampleId'],
        $_POST['sampleCode'],
        $_POST['sampleCollectionDate']
    ];
    if ($_POST === [] || ValidationUtility::validateMandatoryFields($mandatoryFields) === false) {
        $_SESSION['alertMsg'] = _translate("Please enter all mandatory fields to save the test request");
        header("Location:/cd4/requests/cd4-add-request.php");
        die;
    }

    if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
        $resultStatus = RECEIVED_AT_CLINIC;
    } else {
        $resultStatus = RECEIVED_AT_TESTING_LAB;
    }

    //add province
    if (isset($splitProvince[0]) && trim((string) $splitProvince[0]) !== '') {
        $provinceQuery = "SELECT * from geographical_divisions where geo_name=?";
        $provinceInfo = $db->rawQueryOne($provinceQuery, [$splitProvince[0]]);
        if (empty($provinceInfo)) {
            $db->insert(
                'geographical_divisions',
                ['geo_name' => $splitProvince[0], 'geo_code' => $splitProvince[1]]
            );
        }
    }


    if (isset($_POST['newArtRegimen']) && trim((string) $_POST['newArtRegimen']) !== "") {
        $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen
                        WHERE art_code like ?";
        $artResult = $db->rawQueryOne($artQuery);
        if (empty($artResult)) {
            $data = [
                'art_code' => $_POST['newArtRegimen'],
                'parent_art' => 0,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            ];
            $result = $db->insert('r_vl_art_regimen', $data);
            $_POST['artRegimen'] = $_POST['newArtRegimen'];
        } else {
            $_POST['artRegimen'] = $artResult['art_code'];
        }
    }

    try {
        //update facility code
        if (isset($_POST['facilityCode']) && trim((string) $_POST['facilityCode']) !== '') {
            $fData = ['facility_code' => $_POST['facilityCode']];
            $db->where('facility_id', $_POST['facilityId']);
            $id = $db->update('facility_details', $fData);
        }
    } catch (Exception $e) {
        LoggerUtility::logError($e->getFile() . ' : ' . $e->getLine() . ' : ' . $db->getLastError());
        LoggerUtility::logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    if (isset($_POST['gender']) && trim((string) $_POST['gender']) === 'male') {
        $_POST['patientPregnant'] = "N/A";
        $_POST['breastfeeding'] = "N/A";
    }


    $testingPlatform = null;
    $instrumentId = null;
    if (isset($_POST['testingPlatform']) && trim((string) $_POST['testingPlatform']) !== '') {
        $platForm = explode("##", (string) $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
        $instrumentId = $platForm[3];
    }

    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_cd4_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = [
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime()
            ];
            $id = $db->insert('r_cd4_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $db->getInsertId();
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }



    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    if ($_POST['reasonForCD4Testing'] == "baselineInitiation") {
        $lastDate = $_POST['baselineInitiationLastCd4Date'];
        $lastResult = $_POST['baselineInitiationLastCd4Result'];
        $lastResultPercentage = $_POST['baselineInitiationLastCd4ResultPercentage'];
        $lastCrAgResult = $_POST['baselineInitiationLastCrAgResult'];
    } elseif ($_POST['reasonForCD4Testing'] == "assessmentAHD") {
        $lastDate = $_POST['assessmentAHDLastCd4Date'];
        $lastResult = $_POST['assessmentAHDLastCd4Result'];
        $lastResultPercentage = $_POST['assessmentAHDLastCd4ResultPercentage'];
        $lastCrAgResult = $_POST['assessmentAHDLastCrAgResult'];
    } elseif ($_POST['reasonForCD4Testing'] == "treatmentCoinfection") {
        $lastDate = $_POST['treatmentCoinfectionLastCd4Date'];
        $lastResult = $_POST['treatmentCoinfectionLastCd4Result'];
        $lastResultPercentage = $_POST['treatmentCoinfectionLastCd4ResultPercentage'];
        $lastCrAgResult = $_POST['treatmentCoinfectionLastCrAgResult'];
    }

    //set cd4 test reason
    if (isset($_POST['reasonForCD4Testing']) && trim((string) $_POST['reasonForCD4Testing']) !== "" && !is_numeric($_POST['reasonForCD4Testing'])) {
        if ($_POST['reasonForCD4Testing'] == "other") {
            $_POST['reasonForCD4Testing'] = $_POST['newreasonForCD4Testing'];
        }
        $reasonQuery = "SELECT test_reason_id FROM r_cd4_test_reasons
                        WHERE test_reason_name= ?";
        $reasonResult = $db->rawQuery($reasonQuery, [$_POST['reasonForCD4Testing']]);
        if (isset($reasonResult[0]['test_reason_id']) && $reasonResult[0]['test_reason_id'] != '') {
            $_POST['reasonForCD4Testing'] = $reasonResult[0]['test_reason_id'];
        } else {
            $data = ['test_reason_name' => $_POST['reasonForCD4Testing'], 'test_reason_status' => 'active'];
            $id = $db->insert('r_cd4_test_reasons', $data);
            $_POST['reasonForCD4Testing'] = $id;
        }
    }


    //update facility emails
    if (trim((string) $_POST['emailHf']) !== '') {
        $fData = ['facility_emails' => $_POST['emailHf']];
        $db->where('facility_id', $_POST['facilityId']);
        $id = $db->update('facility_details', $fData);
    }


    $_POST['reviewedOn'] = DateUtility::isoDateFormat($_POST['reviewedOn'] ?? '');

    if (isset($_POST['treatmentIndication']) && $_POST['treatmentIndication'] == "Other") {
        $_POST['treatmentIndication'] = $_POST['newTreatmentIndication'] . '_Other';
    }

    // //Update patient Information in Patients Table
    // $patientsService->savePatient($_POST, 'form_cd4');

    // $systemGeneratedCode = $patientsService->getSystemPatientId($_POST['artNo'] ?? '', $_POST['gender'] ?? '', DateUtility::isoDateFormat($_POST['dob'] ?? ''));

    $vlData = [
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => $formId,
        'external_sample_code' => $_POST['serialNo'] ?? null,
        'facility_id' => $_POST['facilityId'] ?? null,
        'sample_collection_date' => DateUtility::isoDateFormat($_POST['sampleCollectionDate'] ?? '', true),
        'sample_dispatched_datetime' => DateUtility::isoDateFormat($_POST['sampleDispatchedDate'] ?? '', true),
        'patient_gender' => $_POST['gender'] ?? null,
        //'system_patient_code' => $systemGeneratedCode,
        'patient_dob' => DateUtility::isoDateFormat($_POST['dob'] ?? ''),
        'patient_age_in_years' => _castVariable($_POST['ageInYears'] ?? null, 'int'),
        'patient_age_in_months' => _castVariable($_POST['ageInMonths'] ?? null, 'int'),
        'is_patient_pregnant' => $_POST['patientPregnant'] ?? null,
        'is_patient_breastfeeding' => $_POST['breastfeeding'] ?? null,
        'patient_art_no' => $_POST['artNo'] ?? null,
        'is_patient_new' => $_POST['isPatientNew'] ?? null,
        'is_patient_initiated_on_art' => $_POST['isPatientInitiatedOnArt'] ?? null,
        'treatment_initiated_date' => DateUtility::isoDateFormat($_POST['dateOfArtInitiation'] ?? ''),
        'current_regimen' => $_POST['artRegimen'] ?? null,
        'has_patient_changed_regimen' => $_POST['hasChangedRegimen'] ?? null,
        'reason_for_regimen_change' => $_POST['reasonForArvRegimenChange'] ?? null,
        'regimen_change_date' => DateUtility::isoDateFormat($_POST['dateOfArvRegimenChange'] ?? ''),
        'date_of_initiation_of_current_regimen' => DateUtility::isoDateFormat($_POST['regimenInitiatedOn'] ?? ''),
        'patient_mobile_number' => $_POST['patientPhoneNumber'] ?? null,
        'consent_to_receive_sms' => $_POST['receiveSms'] ?? 'no',
        'specimen_type' => $_POST['specimenType'] ?? null,
        'sample_reordered' => $_POST['isSampleReordered'] ?? null,
        'arv_adherance_percentage' => $_POST['arvAdherence'] ?? null,
        'reason_for_cd4_testing' => $_POST['reasonForCD4Testing'] ?? null,
        'last_cd4_date' => DateUtility::isoDateFormat($lastDate ?? ''),
        'last_cd4_result' => $lastResult ?? null,
        'last_cd4_result_percentage' => $lastResultPercentage ?? null,
        'last_cd4_crag_result' => $lastCrAgResult ?? null,
        'cd4_result' => $_POST['cd4Result'] ?? null,
        'cd4_result_percentage' => $_POST['cd4ResultPercentage'] ?? null,
        'crag_test_results' => $_POST['crAgResults'] ?? null,
        'request_clinician_name' => $_POST['reqClinician'] ?? null,
        'request_clinician_phone_number' => $_POST['reqClinicianPhoneNumber'] ?? null,
        'test_requested_on' => DateUtility::isoDateFormat($_POST['requestDate'] ?? '', true),
        'cd4_focal_person' => $_POST['cd4FocalPerson'] ?? null,
        'cd4_focal_person_phone_number' => $_POST['cd4FocalPersonPhoneNumber'] ?? null,
        'lab_id' => $_POST['labId'] ?? null,
        'cd4_test_platform' => $testingPlatform ?? null,
        'instrument_id' => $instrumentId ?? null,
        'sample_received_at_hub_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'] ?? '', true),
        'sample_received_at_lab_datetime' => DateUtility::isoDateFormat($_POST['sampleReceivedDate'] ?? '', true),
        'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'] ?? '', true),
        'result_dispatched_datetime' => DateUtility::isoDateFormat($_POST['resultDispatchedOn'] ?? '', true),
        'is_sample_rejected' => $_POST['isSampleRejected'] ?? null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && trim((string) $_POST['rejectionReason']) !== '') ? $_POST['rejectionReason'] : null,
        'rejection_on' => DateUtility::isoDateFormat($_POST['rejectionDate'] ?? ''),
        'result_reviewed_by' => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime' => DateUtility::isoDateFormat($_POST['reviewedOn'] ?? ''),
        'tested_by' => $_POST['testedBy'] ?? null,
        'result_approved_by' => $_POST['approvedBy'] ?? null,
        'result_approved_datetime' => DateUtility::isoDateFormat($_POST['approvedOnDateTime'] ?? '', true),
        'date_test_ordered_by_physician' => DateUtility::isoDateFormat($_POST['dateOfDemand'] ?? ''),
        'lab_tech_comments' => $_POST['labComments'] ?? null,
        'result_status' => $resultStatus,
        'request_created_datetime' => DateUtility::getCurrentDateTime(),
        'last_modified_datetime' => DateUtility::getCurrentDateTime(),
        'result_modified'  => 'no',
        'manual_result_entry' => 'yes',
        'funding_source' => (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') ? base64_decode((string) $_POST['fundingSource']) : null,
        'implementing_partner' => (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') ? base64_decode((string) $_POST['implementingPartner']) : null,
    ];



    if ($general->isLISInstance() || $general->isStandaloneInstance()) {
        $vlData['source_of_request'] = 'vlsm';
    } elseif ($general->isSTSInstance()) {
        $vlData['source_of_request'] = 'vlsts';
    }

    $vlData['request_created_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;
    $vlData['last_modified_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;



    $vlData['patient_first_name'] = $_POST['patientFirstName'] ?? '';
    $vlData['patient_middle_name'] = $_POST['patientMiddleName'] ?? '';
    $vlData['patient_last_name'] = $_POST['patientLastName'] ?? '';

    $vlData['is_encrypted'] = 'no';
    if (isset($_POST['encryptPII']) && $_POST['encryptPII'] == 'yes') {
        $key = (string) $general->getGlobalConfig('key');
        $encryptedPatientId = $general->crypto('encrypt', $vlData['patient_art_no'] ?? '', $key);
        $encryptedPatientFirstName = $general->crypto('encrypt', $vlData['patient_first_name'] ?? '', $key);
        $encryptedPatientMiddleName = $general->crypto('encrypt', $vlData['patient_middle_name'] ?? '', $key);
        $encryptedPatientLastName = $general->crypto('encrypt', $vlData['patient_last_name'] ?? '', $key);

        $vlData['patient_art_no'] = $encryptedPatientId;
        $vlData['patient_first_name'] = $encryptedPatientFirstName;
        $vlData['patient_middle_name'] = $encryptedPatientMiddleName;
        $vlData['patient_last_name'] = $encryptedPatientLastName;
        $vlData['is_encrypted'] = 'yes';
    }

    $id = 0;


    $db->where('cd4_id', $_POST['cd4SampleId']);
    $id = $db->update('form_cd4', $vlData);
    $db->commitTransaction();
    if ($id === true) {
        $_SESSION['alertMsg'] = _translate("CD4 request added successfully");
        $eventType = 'add-vl-request-sudan';
        $action = $_SESSION['userName'] . ' added a new request with the sample id ' . $_POST['sampleCode'];
        $resource = 'vl-request';

        $general->activityLog($eventType, $action, $resource);

        $barcode = "";
        if (!empty($_POST['printBarCode']) && $_POST['printBarCode'] == 'on') {
            $s = $_POST['sampleCode'];
            $facQuery = "SELECT facility_name FROM facility_details where facility_id= ?";
            $facResult = $db->rawQueryOne($facQuery, [$_POST['facilityId']]);
            $f = ($facResult['facility_name']) . " | " . $_POST['sampleCollectionDate'];
            $barcode = "?barcode=true&s=$s&f=$f&p=$patientId";
        }

        if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
            header("Location:/cd4/requests/cd4-add-request.php");
        } else {
            header("Location:/cd4/requests/cd4-requests.php");
        }
    } else {
        $db->rollbackTransaction();
        if ($db->getLastErrno() > 0) {
            LoggerUtility::logError("DB ERROR :: " . $db->getLastError(), [
                'exception' => $db->getLastError(),
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
        $_SESSION['alertMsg'] = _translate("Please try again later");
        header("Location:/cd4/requests/cd4-requests.php");
    }
} catch (Exception $e) {
    $db->rollbackTransaction();
    throw new SystemException($e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), 500, $e);
}
