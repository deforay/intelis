<?php

use App\Models\General;
use App\Models\Vl;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




$general = new General();
$vlModel = new Vl();
$tableName = "form_vl";
$tableName1 = "activity_log";
$vl_result_category = null;
$isRejected = false;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$finalResult = null;
$resultStatus = 6; // Registered at Testing Lab
if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
    $resultStatus = 9; // Resgistered at Collection Site
}
try {
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    $instanceId = '';
    if (isset($_SESSION['instanceId'])) {
        $instanceId = $_SESSION['instanceId'];
    }

    //  //Set Lab ID
    //  $start_date = date('Y-m-01');
    //  $end_date = date('Y-m-31');
    //  $labVlQuery='select MAX(lab_code) FROM form_vl as vl where vl.vlsm_country_id="3" AND DATE(vl.request_created_datetime) >= "'.$start_date.'" AND DATE(vl.request_created_datetime) <= "'.$end_date.'"';
    //  $labVlResult = $db->rawQuery($labVlQuery);
    //  if(isset($labVlResult) && trim($labVlResult[0]['MAX(lab_code)'])!='' && $labVlResult[0]['MAX(lab_code)']!= null){
    //     $_POST['labNo'] = $labVlResult[0]['MAX(lab_code)']+1;
    //  }else{
    //     $_POST['labNo'] = 1;
    //  }

    //Set Date of demand
    if (isset($_POST['dateOfDemand']) && trim($_POST['dateOfDemand']) != "") {
        $_POST['dateOfDemand'] = DateUtils::isoDateFormat($_POST['dateOfDemand']);
    } else {
        $_POST['dateOfDemand'] = null;
    }
    //Set dob
    if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
        $_POST['dob'] = DateUtils::isoDateFormat($_POST['dob']);
    } else {
        $_POST['dob'] = null;
    }
    //Set is patient new
    if (!isset($_POST['isPatientNew']) || trim($_POST['isPatientNew']) == '') {
        $_POST['isPatientNew'] = null;
        $_POST['dateOfArtInitiation'] = null;
    } else if ($_POST['isPatientNew'] == "yes") {
        //Ser ARV initiation date
        if (isset($_POST['dateOfArtInitiation']) && trim($_POST['dateOfArtInitiation']) != "") {
            $_POST['dateOfArtInitiation'] = DateUtils::isoDateFormat($_POST['dateOfArtInitiation']);
        } else {
            $_POST['dateOfArtInitiation'] = null;
        }
    } else if ($_POST['isPatientNew'] == "no") {
        $_POST['dateOfArtInitiation'] = null;
    }
    //Set gender/it's realted values
    if (!isset($_POST['gender']) || trim($_POST['gender']) == '') {
        $_POST['gender'] = null;
        $_POST['breastfeeding'] = null;
        $_POST['patientPregnant'] = null;
        $_POST['trimester'] = null;
    } else if ($_POST['gender'] == "female") {
        if (!isset($_POST['breastfeeding']) || trim($_POST['breastfeeding']) == "") {
            $_POST['breastfeeding'] = null;
        }
        if (!isset($_POST['patientPregnant']) || trim($_POST['patientPregnant']) == "") {
            $_POST['patientPregnant'] = null;
        }
        if (!isset($_POST['trimester']) || trim($_POST['trimester']) == "") {
            $_POST['trimester'] = null;
        }
    } else if ($_POST['gender'] == "male") {
        $_POST['breastfeeding'] = null;
        $_POST['patientPregnant'] = null;
        $_POST['trimester'] = null;
    }

    //Set ARV current regimen
    if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $data = array(
            'art_code' => $_POST['newArtRegimen'],
            'parent_art' => 3,
            'updated_datetime' => DateUtils::getCurrentDateTime(),
        );

        $result = $db->insert('r_vl_art_regimen', $data);
        $_POST['artRegimen'] = $_POST['newArtRegimen'];
    }
    //Regimen change section
    if (!isset($_POST['hasChangedRegimen']) || trim($_POST['hasChangedRegimen']) == '') {
        $_POST['hasChangedRegimen'] = null;
        $_POST['reasonForArvRegimenChange'] = null;
        $_POST['dateOfArvRegimenChange'] = null;
    }
    if (trim($_POST['hasChangedRegimen']) == "no") {
        $_POST['reasonForArvRegimenChange'] = null;
        $_POST['dateOfArvRegimenChange'] = null;
    } else if (trim($_POST['hasChangedRegimen']) == "yes") {
        if (isset($_POST['dateOfArvRegimenChange']) && trim($_POST['dateOfArvRegimenChange']) != "") {
            $_POST['dateOfArvRegimenChange'] = DateUtils::isoDateFormat($_POST['dateOfArvRegimenChange']);
        }
    }
    //Set VL Test reason
    if (isset($_POST['vlTestReason']) && trim($_POST['vlTestReason']) != "") {
        if (trim($_POST['vlTestReason']) == 'other') {
            if (isset($_POST['newVlTestReason']) && trim($_POST['newVlTestReason']) != "") {
                $data = array(
                    'test_reason_name' => $_POST['newVlTestReason'],
                    'test_reason_status' => 'active'
                );
                $id = $db->insert('r_vl_test_reasons', $data);
                $_POST['vlTestReason'] = $id;
            } else {
                $_POST['vlTestReason'] = null;
            }
        }
    } else {
        $_POST['vlTestReason'] = null;
    }
    //Set Viral load no.
    if (!isset($_POST['viralLoadNo']) || trim($_POST['viralLoadNo']) == '') {
        $_POST['viralLoadNo'] = null;
    }
    //Set last VL test date
    if (isset($_POST['lastViralLoadTestDate']) && trim($_POST['lastViralLoadTestDate']) != "") {
        $_POST['lastViralLoadTestDate'] = DateUtils::isoDateFormat($_POST['lastViralLoadTestDate']);
    } else {
        $_POST['lastViralLoadTestDate'] = null;
    }
    //Set sample collection date
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = DateUtils::isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = null;
    }
    //Sample type section
    if (isset($_POST['specimenType']) && trim($_POST['specimenType']) != "") {
        if (trim($_POST['specimenType']) != 2) {
            $_POST['conservationTemperature'] = null;
            $_POST['durationOfConservation'] = null;
        }
    } else {
        $_POST['specimenType'] = null;
        $_POST['conservationTemperature'] = null;
        $_POST['durationOfConservation'] = null;
    }
    //Set sample received date
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = null;
    }
    // //Set sample rejection reason
    // if (isset($_POST['status']) && trim($_POST['status']) != '') {
    //     if ($_POST['status'] == 4) {
    //         if (trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason'] != '')) {
    //             $data = array(
    //                 'rejection_reason_name' => $_POST['newRejectionReason'],
    //                 'rejection_reason_status' => 'active',
    //                 'updated_datetime' => \App\Utilities\DateUtils::getCurrentDateTime(),
    //             );
    //             $id = $db->insert('r_vl_sample_rejection_reasons', $data);
    //             $_POST['rejectionReason'] = $id;
    //         }
    //     } else {
    //         $_POST['rejectionReason'] = null;
    //     }
    // } else {
    //     $_POST['status'] = 6;
    //     $_POST['rejectionReason'] = null;
    // }

    /* if ($_SESSION['instanceType'] == 'remoteuser') {
        $_POST['status'] = 9;
    } */
    //Set result prinetd date time
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestedDate = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = DateUtils::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = null;
    }
    //Set Dispatched From Clinic To Lab Date
    if (isset($_POST['dateDispatchedFromClinicToLab']) && trim($_POST['dateDispatchedFromClinicToLab']) != "") {
        $dispatchedFromClinicToLabDate = explode(" ", $_POST['dateDispatchedFromClinicToLab']);
        $_POST['dateDispatchedFromClinicToLab'] = DateUtils::isoDateFormat($dispatchedFromClinicToLabDate[0]) . " " . $dispatchedFromClinicToLabDate[1];
    } else {
        $_POST['dateDispatchedFromClinicToLab'] = null;
    }
    //Set sample testing date
    if (isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad']) != "") {
        $dateofCompletionofViralLoad = explode(" ", $_POST['dateOfCompletionOfViralLoad']);
        $_POST['dateOfCompletionOfViralLoad'] = DateUtils::isoDateFormat($dateofCompletionofViralLoad[0]) . " " . $dateofCompletionofViralLoad[1];
    } else {
        $_POST['dateOfCompletionOfViralLoad'] = null;
    }
    if (!isset($_POST['sampleCode']) || trim($_POST['sampleCode']) == '') {
        $_POST['sampleCode'] = null;
    }
    $testingPlatform = null;
    if (isset($_POST['testingPlatform']) && trim($_POST['testingPlatform']) != '') {
        $platForm = explode("##", $_POST['testingPlatform']);
        $testingPlatform = $platForm[0];
    }
    if ($_SESSION['instanceType'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
        $sampleCodeKey = 'remote_sample_code_key';
    } else {
        $sampleCode = 'sample_code';
        $sampleCodeKey = 'sample_code_key';
    }

    if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
        $isRejected = true;
        $finalResult = $_POST['vlResult'] = null;
        $_POST['vlLog'] = null;
        $resultStatus = 4;

        if (trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason'] != '')) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        }
    }

    if (isset($_POST['vlResult']) && $_POST['vlResult'] == 'Below Detection Level' && $isRejected === false) {
        $_POST['vlResult'] = 'Below Detection Level';
        $_POST['vlLog'] = null;
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'Failed') || in_array(strtolower($_POST['vlResult']), ['fail', 'failed', 'failure'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Failed';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 5; // Invalid/Failed
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'Error') || in_array(strtolower($_POST['vlResult']), ['error', 'err'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'Error';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 5; // Invalid/Failed
    } else if ((isset($_POST['vlResult']) && $_POST['vlResult'] == 'No Result') || in_array(strtolower($_POST['vlResult']), ['no result', 'no'])) {
        $finalResult = $_POST['vlResult'] = $_POST['vlResult']  ?: 'No Result';
        $_POST['vlLog'] = null;
        $_POST['hivDetection'] = null;
        $resultStatus = 11; // No Result
    } else if (isset($_POST['vlResult']) && trim(!empty($_POST['vlResult']))) {
        $resultStatus = 8; // Awaiting Approval
        $interpretedResults = $vlModel->interpretViralLoadResult($_POST['vlResult']);

        //Result is saved as entered
        $finalResult = $_POST['vlResult'];

        $logVal = $interpretedResults['logVal'];
        $absDecimalVal = $interpretedResults['absDecimalVal'];
        $absVal = $interpretedResults['absVal'];
        $txtVal = $interpretedResults['txtVal'];
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = DateUtils::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = null;
    }

    if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
        $approvedOn = explode(" ", $_POST['approvedOn']);
        $_POST['approvedOn'] = DateUtils::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
    } else {
        $_POST['approvedOn'] = null;
    }
    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => 3,
        'is_sample_rejected' => ($isRejected === true) ? 'yes' : 'no',
        'external_sample_code' => (isset($_POST['serialNo']) && $_POST['serialNo'] != '' ? $_POST['serialNo'] : null),
        'facility_id' => $_POST['fName'],
        'province_id' => (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] :  null,
        'request_clinician_name' => $_POST['clinicianName'],
        'request_clinician_phone_number' => $_POST['clinicanTelephone'],
        'facility_support_partner' => $_POST['implementingPartner'],
        'patient_dob' => $_POST['dob'],
        'patient_age_in_years' => $_POST['ageInYears'],
        'patient_age_in_months' => $_POST['ageInMonths'],
        'patient_gender' => $_POST['gender'],
        'is_patient_breastfeeding' => $_POST['breastfeeding'],
        'is_patient_pregnant' => $_POST['patientPregnant'],
        'pregnancy_trimester' => $_POST['trimester'],
        'patient_art_no' => $_POST['patientArtNo'],
        'is_patient_new' => $_POST['isPatientNew'],
        'date_of_initiation_of_current_regimen' => $_POST['dateOfArtInitiation'],
        'current_regimen' => $_POST['artRegimen'],
        'has_patient_changed_regimen' => $_POST['hasChangedRegimen'],
        'reason_for_regimen_change' => $_POST['reasonForArvRegimenChange'],
        'regimen_change_date' => $_POST['dateOfArvRegimenChange'],
        'reason_for_vl_testing' => $_POST['vlTestReason'],
        'last_viral_load_result' => $_POST['lastViralLoadResult'],
        'last_viral_load_date' => $_POST['lastViralLoadTestDate'],
        'sample_type' => $_POST['specimenType'],
        'plasma_conservation_temperature' => $_POST['conservationTemperature'],
        'plasma_conservation_duration' => $_POST['durationOfConservation'],
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        'result_status' => $resultStatus,
        'reason_for_sample_rejection' => !empty($_POST['rejectionReason']) ? $_POST['rejectionReason'] : null,
        //'sample_code'=>$_POST['sampleCode'],
        //'lab_code'=>$_POST['labNo'],
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] : null),
        'sample_tested_datetime' => $_POST['dateOfCompletionOfViralLoad'],
        'vl_test_platform' => $testingPlatform,
        'result_value_absolute'                 => $absVal ?: null,
        'result_value_absolute_decimal'         => $absDecimalVal ?: null,
        'result_value_text'                     => $txtVal ?: null,
        'result'                                => $finalResult ?: null,
        'result_value_log'                      => $logVal ?: null,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by'        => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
        'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  null,
        'date_test_ordered_by_physician' => $_POST['dateOfDemand'],
        'funding_source' => (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') ? base64_decode($_POST['fundingSource']) : null,
        'implementing_partner' => (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') ? base64_decode($_POST['implementingPartner']) : null,
        'vl_test_number' => $_POST['viralLoadNo'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'sample_dispatched_datetime' => $_POST['dateDispatchedFromClinicToLab'],
        //'result_printed_datetime'=>$_POST['sampleTestingDateAtLab'],
        'result_value_hiv_detection' => (isset($_POST['hivDetection']) && $_POST['hivDetection'] != '') ? $_POST['hivDetection'] :  null,
        'reason_for_failure' => (isset($_POST['reasonForFailure']) && $_POST['reasonForFailure'] != '') ? $_POST['reasonForFailure'] :  null,
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $db->now(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $db->now(),
        'vl_result_category' => $vl_result_category
    );

    if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "vluser" || $sarr['sc_user_type'] == "standalone")) {
        $vldata['source_of_request'] = 'vlsm';
    } else if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "remoteuser")) {
        $vldata['source_of_request'] = 'vlsts';
    } else if (!empty($_POST['api']) && $_POST['api'] == "yes") {
        $vldata['source_of_request'] = 'api';
    }


    /* Updating the high and low viral load data */
    //if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
    $vldata['vl_result_category'] = $vlModel->getVLResultCategory($vldata['result_status'], $vldata['result']);
    if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
        $vldata['result_status'] = 5;
    } elseif ($vldata['vl_result_category'] == 'rejected') {
        $vldata['result_status'] = 4;
    }
    //}
    $id = 0;
    if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
        $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
        $id = $db->update($tableName, $vldata);
        // error_log($db->getLastError());
    }
    // else {
    //     //check existing sample code
    //     $existSampleQuery = "SELECT " . $sampleCode . "," . $sampleCodeKey . " FROM form_vl where " . $sampleCode . " ='" . trim($_POST['sampleCode']) . "'";
    //     $existResult = $db->rawQuery($existSampleQuery);
    //     if (isset($existResult[0][$sampleCodeKey]) && $existResult[0][$sampleCodeKey] != '') {
    //         if ($existResult[0][$sampleCodeKey] != '') {
    //             $sCode = $existResult[0][$sampleCodeKey] + 1;
    //             $strparam = strlen($sCode);
    //             $zeros = substr("000", $strparam);
    //             $maxId = $zeros . $sCode;
    //             $_POST['sampleCode'] = $_POST['sampleCodeFormat'] . $maxId;
    //             $_POST['sampleCodeKey'] = $maxId;
    //         } else {
    //             $_SESSION['alertMsg'] = "Please check your sample ID";
    //             header("Location:addVlRequest.php");
    //         }
    //     }

    //     if ($_SESSION['instanceType'] == 'remoteuser') {
    //         $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
    //         $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
    //         $vldata['remote_sample'] = 'yes';
    //     } else {
    //         $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
    //         $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
    //         $vldata['sample_registered_at_lab'] = \App\Utilities\DateUtils::getCurrentDateTime();
    //     }
    //     $vldata['sample_code_format'] = (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '' ? $_POST['sampleCodeFormat'] : null);
    //     $id = $db->insert($tableName, $vldata);
    // }
    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL request added successfully";
        //Add event log
        $eventType = 'add-vl-request-drc';
        $action = $_SESSION['userName'] . ' added a new VL Test request for Patient Code ' . $_POST['patientArtNo'];
        $resource = 'vl-request-drc';

        $general->activityLog($eventType, $action, $resource);

        // $data=array(
        // 'event_type'=>$eventType,
        // 'action'=>$action,
        // 'resource'=>$resource,
        // 'date_time'=>\App\Utilities\DateUtils::getCurrentDateTime()
        // );
        // $db->insert($tableName1,$data);

    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("Location:vlRequest.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
