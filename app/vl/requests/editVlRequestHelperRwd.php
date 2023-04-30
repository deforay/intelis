<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



//echo "<pre>";var_dump($_POST);die;
/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

/** @var VlService $vlService */
$vlService = \App\Registries\ContainerRegistry::get(VlService::class);
$dateUtils = new DateUtility();

$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$fDetails = "facility_details";

$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;
$resultStatus = null;

try {
    $validateField = array($_POST['sampleCode'], $_POST['sampleCollectionDate']);
    $chkValidation = $general->checkMandatoryFields($validateField);
    if ($chkValidation) {
        $_SESSION['alertMsg'] = "Please enter all mandatory fields to save the test request";
        header("Location:editVlRequest.php?id=" . base64_encode($_POST['vlSampleId']));
        die;
    }
    $configQuery = "SELECT * from global_config";
    $configResult = $db->query($configQuery);
    $arr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
        $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    // if ($sarr['sc_user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
    //     $_POST['status'] = 9;
    // } else if ($_POST['oldStatus'] == 9) {
    //     $_POST['status'] = 6;
    // }
    // if ($_POST['status'] == '') {
    //     $_POST['status']  = $_POST['oldStatus'];
    // }
    //add province
    $splitProvince = explode("##", $_POST['province']);
    if (isset($splitProvince[0]) && trim($splitProvince[0]) != '') {
        $provinceQuery = "SELECT * from geographical_divisions where geo_name='" . $splitProvince[0] . "'";
        $provinceInfo = $db->query($provinceQuery);
        if (!isset($provinceInfo) || count($provinceInfo) == 0) {
            $db->insert('geographical_divisions', array('geo_name' => $splitProvince[0], 'geo_code' => $splitProvince[1]));
        }
    }
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }

    if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
        $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
    } else {
        $_POST['dob'] = null;
    }

    if (isset($_POST['dateOfArtInitiation']) && trim($_POST['dateOfArtInitiation']) != "") {
        $_POST['dateOfArtInitiation'] = DateUtility::isoDateFormat($_POST['dateOfArtInitiation']);
    } else {
        $_POST['dateOfArtInitiation'] = null;
    }

    if (isset($_POST['regimenInitiatedOn']) && trim($_POST['regimenInitiatedOn']) != "") {
        $_POST['regimenInitiatedOn'] = DateUtility::isoDateFormat($_POST['regimenInitiatedOn']);
    } else {
        $_POST['regimenInitiatedOn'] = null;
    }

    if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen where (art_code='" . $_POST['newArtRegimen'] . "' OR art_code='" . strtolower($_POST['newArtRegimen']) . "' OR art_code='" . (strtolower($_POST['newArtRegimen'])) . "')";
        $artResult = $db->rawQuery($artQuery);
        if (!isset($artResult[0]['art_id'])) {
            $data = array(
                'art_code' => $_POST['newArtRegimen'],
                'parent_art' => '7',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $result = $db->insert('r_vl_art_regimen', $data);
            $_POST['artRegimen'] = $_POST['newArtRegimen'];
        } else {
            $_POST['artRegimen'] = $artResult[0]['art_code'];
        }
    }

    //update facility code
    if (trim($_POST['fCode']) != '') {
        $fData = array('facility_code' => $_POST['fCode']);
        $db = $db->where('facility_id', $_POST['fName']);
        $id = $db->update($fDetails, $fData);
    }
    //update facility emails
    if (trim($_POST['emailHf']) != '') {
        $fData = array('facility_emails' => $_POST['emailHf']);
        $db = $db->where('facility_id', $_POST['fName']);
        $id = $db->update($fDetails, $fData);
    }
    if (!isset($_POST['gender']) || trim($_POST['gender']) != 'female') {
        $_POST['patientPregnant'] = '';
        $_POST['breastfeeding'] = '';
    }
    $instanceId = '';
    if (isset($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }
    $testingPlatform = '';
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDateLab = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateAtLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
        $resultDispatchedOn = explode(" ", $_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
    } else {
        $_POST['resultDispatchedOn'] = null;
    }

    if (isset($_POST['newRejectionReason']) && trim($_POST['newRejectionReason']) != "") {
        $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_vl_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower($_POST['newRejectionReason']) . "' OR rejection_reason_name='" . (strtolower($_POST['newRejectionReason'])) . "'";
        $rejectionResult = $db->rawQuery($rejectionReasonQuery);
        if (!isset($rejectionResult[0]['rejection_reason_id'])) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
        }
    }

    $isRejected = false;

    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = null;
        $_POST['vlLog'] = null;
        $resultStatus = 4;
    }

    if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = 'Target Not Detected';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['lt20']) && $_POST['lt20'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['lt40']) && $_POST['lt40'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    } else if (isset($_POST['bdl']) && $_POST['bdl'] == 'yes' && $isRejected === false) {
        $_POST['vlResult'] = 'Below Detection Level';
        $_POST['vlLog'] = '';
    }

    if (
        (isset($_POST['failed']) && $_POST['failed'] == 'yes')
        || in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure', 'error', 'err'])
    ) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Failed';
        $_POST['vlLog'] = '';
        $resultStatus = 5; // Invalid/Failed
    } else if (isset($_POST['invalid']) && $_POST['invalid'] == 'yes' && $isRejected === false) {
        $finalResult = $_POST['vlResult'] = 'Invalid';
        $_POST['vlLog'] = '';
        $resultStatus = 5; // Invalid/Failed
    } else if (isset($_POST['vlResult']) && trim(!empty($_POST['vlResult']))) {

        $resultStatus = 8; // Awaiting Approval    

        $interpretedResults = $vlService->interpretViralLoadResult($_POST['vlResult']);

        //Result is saved as entered
        $finalResult  = $_POST['vlResult'];

        $logVal = $interpretedResults['logVal'];
        $absDecimalVal = $interpretedResults['absDecimalVal'];
        $absVal = $interpretedResults['absVal'];
        $txtVal = $interpretedResults['txtVal'];
    }

    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . DateUtility::getCurrentDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }
    //Indication for Viral Load Testing
    $rmVLValue = null;
    if (isset($_POST['reasonForVLTesting']) && $_POST['reasonForVLTesting'] == 'routine') {
        if (isset($_POST['rmTestingVlValue']) && $_POST['rmTestingVlValue'] != '') {
            $rmVLValue = $_POST['rmTestingVlValue'];
        } else if (isset($_POST['rmTestingVlCheckValue']) && $_POST['rmTestingVlCheckValue'] != '') {
            $rmVLValue = $_POST['rmTestingVlCheckValue'];
        }
    }
    $repeatTestingVLValue = null;
    if (isset($_POST['reasonForVLTesting']) && $_POST['reasonForVLTesting'] == 'failure') {
        if (isset($_POST['repeatTestingVlValue']) && $_POST['repeatTestingVlValue'] != '') {
            $repeatTestingVLValue = $_POST['repeatTestingVlValue'];
        } else if (isset($_POST['repeatTestingVlCheckValue']) && $_POST['repeatTestingVlCheckValue'] != '') {
            $repeatTestingVLValue = $_POST['repeatTestingVlCheckValue'];
        }
    }
    $suspendedTreatmentVLValue = null;
    if (isset($_POST['reasonForVLTesting']) && $_POST['reasonForVLTesting'] == 'suspect') {
        if (isset($_POST['suspendTreatmentVlValue']) && $_POST['suspendTreatmentVlValue'] != '') {
            $suspendedTreatmentVLValue = $_POST['suspendTreatmentVlValue'];
        } else if (isset($_POST['suspendTreatmentVlCheckValue']) && $_POST['suspendTreatmentVlCheckValue'] != '') {
            $suspendedTreatmentVLValue = $_POST['suspendTreatmentVlCheckValue'];
        }
    }

    //set vl test reason
    if (isset($_POST['reasonForVLTesting']) && trim($_POST['reasonForVLTesting']) != "") {
        $reasonQuery = "SELECT test_reason_id FROM r_vl_test_reasons where test_reason_name='" . $_POST['reasonForVLTesting'] . "'";
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

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if(isset($_POST['dob']) && $_POST['dob'] != '')
    {
        $ageInfo = $dateUtils->ageInYearMonthDays($_POST['dob']);
        $ageInYears = $ageInfo['year'];
        if($ageInYears < 1){
            $ageInMonths = ($ageInYears * 12) + $ageInfo['months'];
        }
        else
        {
            $ageInMonths = 0;
        }
    }
    else
    {
        $ageInYears = $_POST['ageInYears'];
        $ageInMonths = $_POST['ageInMonths'];
    }

    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'sample_reordered' => (isset($_POST['sampleReordered']) && $_POST['sampleReordered'] != '') ? $_POST['sampleReordered'] : 'no',
        //'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='') ? $_POST['sampleCode'] :  null,
        'facility_id' => (isset($_POST['fName']) && $_POST['fName'] != '') ? $_POST['fName'] : null,
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        //'patient_first_name'=>(isset($_POST['patientFirstName']) && $_POST['patientFirstName']!='') ? $_POST['patientFirstName'] :  null,
        'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] : null,
        'patient_dob' => $_POST['dob'],
        'patient_age_in_years' => $ageInYears,
        'patient_age_in_months' => $ageInMonths,
        'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] : null,
        'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] : null,
        'patient_art_no' => (isset($_POST['artNo']) && $_POST['artNo'] != '') ? $_POST['artNo'] : null,
        'treatment_initiated_date' => $_POST['dateOfArtInitiation'],
        'current_regimen' => (isset($_POST['artRegimen']) && $_POST['artRegimen'] != '') ? $_POST['artRegimen'] : null,
        'date_of_initiation_of_current_regimen' => $_POST['regimenInitiatedOn'],
        'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] : null,
        'sample_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] : null,
        'arv_adherance_percentage' => (isset($_POST['arvAdherence']) && $_POST['arvAdherence'] != '') ? $_POST['arvAdherence'] : null,
        'reason_for_vl_testing' => (isset($_POST['reasonForVLTesting'])) ? $_POST['reasonForVLTesting'] : null,
        'last_vl_date_routine' => (isset($_POST['rmTestingLastVLDate']) && $_POST['rmTestingLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['rmTestingLastVLDate']) : null,
        'last_vl_result_routine' => $rmVLValue,
        'last_vl_date_failure_ac' => (isset($_POST['repeatTestingLastVLDate']) && $_POST['repeatTestingLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['repeatTestingLastVLDate']) : null,
        'last_vl_result_failure_ac' => $repeatTestingVLValue,
        'last_vl_date_failure' => (isset($_POST['suspendTreatmentLastVLDate']) && $_POST['suspendTreatmentLastVLDate'] != '') ? DateUtility::isoDateFormat($_POST['suspendTreatmentLastVLDate']) : null,
        'last_vl_result_failure' => $suspendedTreatmentVLValue,
        'request_clinician_name' => (isset($_POST['reqClinician']) && $_POST['reqClinician'] != '') ? $_POST['reqClinician'] : null,
        'request_clinician_phone_number' => (isset($_POST['reqClinicianPhoneNumber']) && $_POST['reqClinicianPhoneNumber'] != '') ? $_POST['reqClinicianPhoneNumber'] : null,
        'test_requested_on' => (isset($_POST['requestDate']) && $_POST['requestDate'] != '') ? DateUtility::isoDateFormat($_POST['requestDate']) : null,
        'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] : null,
        'vl_focal_person_phone_number' => (isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber'] != '') ? $_POST['vlFocalPersonPhoneNumber'] : null,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] : null,
        'vl_test_platform' => $testingPlatform,
        //'test_methods'=>(isset($_POST['testMethods']) && $_POST['testMethods']!='') ? $_POST['testMethods'] :  null,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
        'reason_for_failure' => (isset($_POST['reasonForFailure']) && $_POST['reasonForFailure'] != '') ? $_POST['reasonForFailure'] :  null,
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] : null,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : null,
        'result_value_absolute'                 => $absVal ?: null,
        'result_value_absolute_decimal'         => $absDecimalVal ?: null,
        'result_value_text'                     => $txtVal ?: null,
        'result'                                => $finalResult ?: null,
        'result_value_log'                      => $logVal ?: null,
        //'result_status'                         => $resultStatus,
        //'result_status' => (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] : null,        
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
        'lab_tech_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) : null,
        'reason_for_vl_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'manual_result_entry' => 'yes',
        'data_sync' => 0
    );


    // only if result status has changed, let us update
    if (!empty($resultStatus)) {
        $vldata['result_status'] = $resultStatus;
    }

    $vldata['vl_result_category'] = $vlService->getVLResultCategory($vldata['result_status'], $vldata['result']);
    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
    }

    $vldata['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFirstName'], $vldata['patient_art_no']);

    //var_dump($vldata);die;

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vldata);

    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL request updated successfully";
        //Add event log
        $eventType = 'update-vl-request-rwd';
        $action = $_SESSION['userName'] . ' updated a request data with the sample code ' . $_POST['sampleCode'];
        $resource = 'vl-request-rwd';

        $general->activityLog($eventType, $action, $resource);
    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("Location:vlRequest.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
