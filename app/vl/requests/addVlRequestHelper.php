<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$fDetails = "facility_details";
$vl_result_category = null;
$finalResult = null;

$systemType = $general->getSystemConfig('sc_user_type');
$formId = $general->getGlobalConfig('vl_form');


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$instanceId = $general->getInstanceId();

try {

    $validateField = array($_POST['vlSampleId'], $_POST['sampleCode'], $_POST['sampleCollectionDate']);
    $chkValidation = $general->checkMandatoryFields($validateField);
    if ($chkValidation) {
        $_SESSION['alertMsg'] = _("Please enter all mandatory fields to save the test request");
        header("Location:addVlRequest.php");
        die;
    }


    $resultStatus = 6;

    if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
        $resultStatus = 9;
    }

    //add province
    if (isset($splitProvince[0]) && trim($splitProvince[0]) != '') {
        $provinceQuery = "SELECT * from geographical_divisions where geo_name=?";
        $provinceInfo = $db->rawQueryOne($provinceQuery, [$splitProvince[0]]);
        if (empty($provinceInfo)) {
            $db->insert(
                'geographical_divisions',
                ['geo_name' => $splitProvince[0], 'geo_code' => $splitProvince[1]]
            );
        }
    }


    if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen
                        WHERE art_code like ?";
        $artResult = $db->rawQueryOne($artQuery);
        if (empty($artResult)) {
            $data = array(
                'art_code' => $_POST['newArtRegimen'],
                'parent_art' => $formId,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $result = $db->insert('r_vl_art_regimen', $data);
            $_POST['artRegimen'] = $_POST['newArtRegimen'];
        } else {
            $_POST['artRegimen'] = $artResult['art_code'];
        }
    }


    //update facility code
    if (isset($_POST['fCode']) && trim($_POST['fCode']) != '') {
        $fData = array('facility_code' => $_POST['fCode']);
        $db = $db->where('facility_id', $_POST['fName']);
        $id = $db->update($fDetails, $fData);
    }
    if (isset($_POST['gender']) && trim($_POST['gender']) == 'male') {
        $_POST['patientPregnant'] = null;
        $_POST['breastfeeding'] = null;
    }


    $testingPlatform = null;
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }

    if (!empty($_POST['newRejectionReason'])) {
        $rejectionReasonQuery = "SELECT rejection_reason_id
                    FROM r_vl_sample_rejection_reasons
                    WHERE rejection_reason_name like ?";
        $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
        if (empty($rejectionResult)) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult['rejection_reason_id'];
        }
    }

    $isRejected = false;
    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vl_result_category = 'rejected';
        $isRejected = true;
        $resultStatus = 4;
        $_POST['vlResult'] = null;
        $_POST['vlLog'] = null;
    }


    $_POST['result'] = null;
    if (!empty($_POST['vlResult'])) {

        $_POST['result'] = $_POST['vlResult'];
        $resultStatus = 8; // Awaiting Approval

        if (in_array(strtolower($_POST['vlResult']), ['bdl', 'below detection level'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Below Detection Level';
            $_POST['vlResult'] = 'Below Detection Level';
            $_POST['vlLog'] = null;
        } elseif (in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Failed';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 5; // Invalid/Failed
        } elseif (in_array(strtolower($_POST['vlResult']), ['error', 'err'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'Error';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 5; // Invalid/Failed
        } elseif (in_array(strtolower($_POST['vlResult']), ['no result', 'no'])) {
            $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?? 'No Result';
            $_POST['vlLog'] = null;
            $_POST['hivDetection'] = null;
            $resultStatus = 11; // No Result
        } else {

            $interpretedResults = $vlService->interpretViralLoadResult($_POST['vlResult']);

            //Result is saved as entered
            $finalResult  = $_POST['vlResult'];

            $logVal = $interpretedResults['logVal'] ?? null;
            $absDecimalVal = $interpretedResults['absDecimalVal'] ?? null;
            $absVal = $interpretedResults['absVal'] ?? null;
            $txtVal = $interpretedResults['txtVal'] ?? null;
        }
    } elseif (!empty($_POST['vlLog'])) {
        $resultStatus = 8; // Awaiting Approval
        $finalResult = $_POST['result'] = pow(10, $_POST['vlLog']);
    }

    $finalResult = (isset($_POST['hivDetection']) && $_POST['hivDetection'] != '') ? $_POST['hivDetection'] . ' ' . $finalResult :  $finalResult;

    if ($_SESSION['instanceType'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    //set vl test reason
    if (isset($_POST['reasonForVLTesting']) && trim($_POST['reasonForVLTesting']) != "") {
        if (!is_numeric($_POST['reasonForVLTesting'])) {
            $reasonQuery = "SELECT test_reason_id FROM r_vl_test_reasons
                        WHERE test_reason_name='" . $_POST['reasonForVLTesting'] . "'";
            $reasonResult = $db->rawQuery($reasonQuery);
            if (isset($reasonResult[0]['test_reason_id']) && $reasonResult[0]['test_reason_id'] != '') {
                $_POST['reasonForVLTesting'] = $reasonResult[0]['test_reason_id'];
            } else {
                $data = array(
                    'test_reason_name' => $_POST['reasonForVLTesting'],
                    'test_reason_status' => 'active'
                );
                $id = $db->insert('r_vl_test_reasons', $data);
                $_POST['reasonForVLTesting'] = $id;
            }
        }
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if (isset($_POST['treatmentIndication']) && $_POST['treatmentIndication'] == "Other") {
        $_POST['treatmentIndication'] = $_POST['newTreatmentIndication'] . '_Other';
    }



    $vlData = array(
        'vlsm_instance_id'                      => $instanceId,
        'vlsm_country_id'                       => $formId ?? 1,
        'sample_reordered'                      => $_POST['sampleReordered'] ?? 'no',
        'external_sample_code'                  => $_POST['serialNo'] ?? null,
        'facility_id'                           => $_POST['fName'] ?? null,
        'sample_collection_date'                => DateUtility::isoDateFormat($_POST['sampleCollectionDate'], true),
        'sample_dispatched_datetime'            => DateUtility::isoDateFormat($_POST['sampleDispatchedDate'], true),
        'patient_gender'                        => $_POST['gender'] ?? null,
        'patient_dob'                           => DateUtility::isoDateFormat($_POST['dob']),
        'patient_age_in_years'                  => $_POST['ageInYears'] ?? null,
        'patient_age_in_months'                 => $_POST['ageInMonths'] ?? null,
        'is_patient_pregnant'                   => $_POST['patientPregnant'] ?? null,
        'is_patient_breastfeeding'              => $_POST['breastfeeding'] ?? null,
        'pregnancy_trimester'                   => $_POST['trimester'] ?? null,
        'patient_has_active_tb'                 => $_POST['activeTB'] ?? null,
        'patient_active_tb_phase'               => $_POST['tbPhase'] ?? null,
        'patient_art_no'                        => $_POST['artNo'] ?? null,
        'is_patient_new'                        => $_POST['isPatientNew'] ?? null,
        'treatment_duration'                    => $_POST['treatmentDuration'] ?? null,
        'treatment_indication'                  => $_POST['treatmentIndication'] ?? null,
        'treatment_initiated_date'              => DateUtility::isoDateFormat($_POST['dateOfArtInitiation']),
        'current_regimen'                       => $_POST['artRegimen'] ?? null,
        'has_patient_changed_regimen'           => $_POST['hasChangedRegimen'] ?? null,
        'reason_for_regimen_change'             => $_POST['reasonForArvRegimenChange'] ?? null,
        'regimen_change_date'                   => DateUtility::isoDateFormat($_POST['dateOfArvRegimenChange']),
        'line_of_treatment'                     => $_POST['lineOfTreatment'] ?? null,
        'line_of_treatment_failure_assessed'    => $_POST['lineOfTreatmentFailureAssessed'] ?? null,
        'date_of_initiation_of_current_regimen' => DateUtility::isoDateFormat($_POST['regimenInitiatedOn']),
        'patient_mobile_number'                 => $_POST['patientPhoneNumber'] ?? null,
        'consent_to_receive_sms'                => $_POST['receiveSms'] ?? null,
        'sample_type'                           => $_POST['specimenType'] ?? null,
        'plasma_conservation_temperature'       => $_POST['conservationTemperature'] ?? null,
        'plasma_conservation_duration'          => $_POST['durationOfConservation'] ?? null,
        'arv_adherance_percentage'              => $_POST['arvAdherence'] ?? null,
        'reason_for_vl_testing'                 => $_POST['reasonForVLTesting'] ?? null,
        'last_viral_load_result'                => $_POST['lastViralLoadResult'] ?? null,
        'last_viral_load_date'                  => DateUtility::isoDateFormat($_POST['lastViralLoadTestDate']),
        'community_sample'                      => $_POST['communitySample'] ?? null,
        'last_vl_date_routine'                  => DateUtility::isoDateFormat($_POST['rmTestingLastVLDate']),
        'last_vl_result_routine'                => $_POST['rmTestingVlValue'] ?? null,
        'last_vl_sample_type_routine'           => $_POST['rmLastVLTestSampleType'] ?? null,
        'last_vl_date_failure_ac'               => DateUtility::isoDateFormat($_POST['repeatTestingLastVLDate']),
        'last_vl_result_failure_ac'             => $_POST['repeatTestingVlValue'] ?? null,
        'last_vl_sample_type_failure_ac'        => $_POST['repeatLastVLTestSampleType'] ?? null,
        'last_vl_date_failure'                  => DateUtility::isoDateFormat($_POST['suspendTreatmentLastVLDate']),
        'last_vl_result_failure'                => $_POST['suspendTreatmentVlValue'] ?? null,
        'last_vl_sample_type_failure'           => $_POST['suspendLastVLTestSampleType'] ?? null,
        'request_clinician_name'                => $_POST['reqClinician'] ?? null,
        'request_clinician_phone_number'        => $_POST['reqClinicianPhoneNumber'] ?? null,
        'test_requested_on'                     => DateUtility::isoDateFormat($_POST['requestDate']),
        'vl_focal_person'                       => $_POST['vlFocalPerson'] ?? null,
        'vl_focal_person_phone_number'          => $_POST['vlFocalPersonPhoneNumber'] ?? null,
        'lab_id'                                => $_POST['labId'] ?? null,
        'vl_test_platform'                      => $testingPlatform ?? null,
        'sample_received_at_hub_datetime'       =>  DateUtility::isoDateFormat($_POST['sampleReceivedAtHubOn'], true),
        'sample_received_at_vl_lab_datetime'    => DateUtility::isoDateFormat($_POST['sampleReceivedDate'], true),
        'sample_tested_datetime'                => DateUtility::isoDateFormat($_POST['sampleTestingDateAtLab'], true),
        'result_dispatched_datetime'            => DateUtility::isoDateFormat($_POST['resultDispatchedOn'], true),
        'result_value_hiv_detection'            => $_POST['hivDetection'] ?? null,
        'reason_for_failure'                    => $_POST['reasonForFailure'] ??  null,
        'is_sample_rejected'                    => $_POST['noResult'] ??  null,
        'reason_for_sample_rejection'           => $_POST['rejectionReason'] ??  null,
        'rejection_on'                          => DateUtility::isoDateFormat($_POST['rejectionDate']),
        'result_value_absolute'                 => $absVal  ?? null,
        'result_value_absolute_decimal'         => $absDecimalVal ?? null,
        'result_value_text'                     => $txtVal ?? null,
        'result'                                => $finalResult ?? null,
        'result_value_log'                      => $logVal ?? null,
        'result_reviewed_by'                    => $_POST['reviewedBy'] ?? null,
        'result_reviewed_datetime'              => $_POST['reviewedOn'] ?? null,
        'tested_by'                             => $_POST['testedBy'] ?? null,
        'result_approved_by'                    => $_POST['approvedBy'] ?? null,
        'result_approved_datetime'              => DateUtility::isoDateFormat($_POST['approvedOnDateTime'], true),
        'date_test_ordered_by_physician'        => DateUtility::isoDateFormat($_POST['dateOfDemand']),
        'lab_tech_comments'                     => $_POST['labComments'] ?? null,
        'result_status'                         => $resultStatus,
        'funding_source'                        => (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') ? base64_decode($_POST['fundingSource']) : null,
        'implementing_partner'                  => (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') ? base64_decode($_POST['implementingPartner']) : null,
        'vl_test_number'                        => $_POST['viralLoadNo'] ?? null,
        'request_created_datetime'              => DateUtility::getCurrentDateTime(),
        'last_modified_datetime'                => DateUtility::getCurrentDateTime(),
        'manual_result_entry'                   => 'yes'
    );

    if (isset($systemType) && ($systemType == "vluser" || $systemType == "standalone")) {
        $vlData['source_of_request'] = 'vlsm';
    } elseif (isset($systemType) && ($systemType == "remoteuser")) {
        $vlData['source_of_request'] = 'vlsts';
    } elseif (!empty($_POST['api']) && $_POST['api'] == "yes") {
        $vlData['source_of_request'] = 'api';
    }

    $vlData['request_created_by'] =  $_SESSION['userId'] ?? $_POST['userId'] ?? null;
    $vlData['last_modified_by'] =  $_SESSION['userId'] ?? $_POST['userId'] ?? null;

    $vlData['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFirstName'], $vlData['patient_art_no']);



    $vlData['vl_result_category'] = $vlService->getVLResultCategory($vlData['result_status'], $vlData['result']);
    if ($vlData['vl_result_category'] == 'failed' || $vlData['vl_result_category'] == 'invalid') {
        $vlData['result_status'] = 5;
    } elseif ($vlData['vl_result_category'] == 'rejected') {
        $vlData['result_status'] = 4;
    }



    //For PNG form
    $pngSpecificFields = [];
    if (isset($formId) && $formId == '5') {

        if (isset($_POST['failedTestingTech']) && $_POST['failedTestingTech'] != '') {
            $platForm = explode("##", $_POST['failedTestingTech']);
            $_POST['failedTestingTech'] = $platForm[0];
        }


        $pngSpecificFields['art_cd_cells'] = $_POST['cdCells'];
        $pngSpecificFields['art_cd_date'] = DateUtility::isoDateFormat($_POST['cdDate']);
        $pngSpecificFields['who_clinical_stage'] = $_POST['clinicalStage'];
        $pngSpecificFields['sample_to_transport'] = $_POST['typeOfSample'] ?? null;
        $pngSpecificFields['whole_blood_ml'] = $_POST['wholeBloodOne'] ?? null;
        $pngSpecificFields['whole_blood_vial'] = $_POST['wholeBloodTwo'] ?? null;
        $pngSpecificFields['plasma_ml'] = $_POST['plasmaOne'] ?? null;
        $pngSpecificFields['plasma_vial'] = $_POST['plasmaTwo'] ?? null;
        $pngSpecificFields['plasma_process_time'] = $_POST['processTime'] ?? null;
        $pngSpecificFields['plasma_process_tech'] = $_POST['processTech'] ?? null;
        $pngSpecificFields['sample_collected_by'] = $_POST['collectedBy'] ?? null;
        $pngSpecificFields['tech_name_png'] = $_POST['techName'] ?? null;
        $pngSpecificFields['cphl_vl_result'] = $_POST['cphlvlResult'] ?? null;
        $pngSpecificFields['batch_quality'] = $_POST['batchQuality'] ?? null;
        $pngSpecificFields['sample_test_quality'] = $_POST['testQuality'] ?? null;
        $pngSpecificFields['sample_batch_id'] = $_POST['batchNo'] ?? null;
        $pngSpecificFields['failed_test_date'] = DateUtility::isoDateFormat($_POST['failedTestDate'], true);
        $pngSpecificFields['failed_test_tech'] = $_POST['failedTestingTech'] ?? null;
        $pngSpecificFields['failed_vl_result'] = $_POST['failedvlResult'] ?? null;
        $pngSpecificFields['failed_batch_quality'] = $_POST['failedbatchQuality'] ?? null;
        $pngSpecificFields['failed_sample_test_quality'] = $_POST['failedtestQuality'] ?? null;
        $pngSpecificFields['failed_batch_id'] = $_POST['failedbatchNo'] ?? null;
        $pngSpecificFields['result'] = $_POST['finalViralResult'] ?? null;
        $pngSpecificFields['qc_tech_name'] = $_POST['qcTechName'] ?? null;
        $pngSpecificFields['qc_tech_sign'] = $_POST['qcTechSign'] ?? null;
        $pngSpecificFields['qc_date'] = DateUtility::isoDateFormat($_POST['qcDate']);
        $pngSpecificFields['report_date'] = DateUtility::isoDateFormat($_POST['reportDate']);
    }
    $vlData = array_merge($vlData, $pngSpecificFields);

    $vlData['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFirstName'], $vlData['patient_art_no']);
    $id = 0;

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vlData);


    if ($id === true) {
        $_SESSION['alertMsg'] = _("VL request added successfully");
        $eventType = 'add-vl-request-sudan';
        $action = $_SESSION['userName'] . ' added a new request with the sample code ' . $_POST['sampleCode'];
        $resource = 'vl-request';

        $general->activityLog($eventType, $action, $resource);

        $barcode = "";
        if (isset($_POST['printBarCode']) && $_POST['printBarCode'] == 'on') {
            $s = $_POST['sampleCode'];
            $facQuery = "SELECT * FROM facility_details where facility_id= ?";
            $facResult = $db->rawQuery($facQuery, [$_POST['fName']]);
            $f = ($facResult[0]['facility_name']) . " | " . $_POST['sampleCollectionDate'];
            $barcode = "?barcode=true&s=$s&f=$f";
        }

        if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
            header("Location:addVlRequest.php");
        } else {
            header("Location:vlRequest.php");
        }
    } else {
        $_SESSION['alertMsg'] = _("Please try again later");
        header("Location:vlRequest.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
