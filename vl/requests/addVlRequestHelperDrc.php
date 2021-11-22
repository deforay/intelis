<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');  


$general = new \Vlsm\Models\General();
$tableName = "vl_request_form";
$tableName1 = "activity_log";
$vl_result_category = NULL;
try {
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = array();
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
    //  $labVlQuery='select MAX(lab_code) FROM vl_request_form as vl where vl.vlsm_country_id="3" AND DATE(vl.request_created_datetime) >= "'.$start_date.'" AND DATE(vl.request_created_datetime) <= "'.$end_date.'"';
    //  $labVlResult = $db->rawQuery($labVlQuery);
    //  if(isset($labVlResult) && trim($labVlResult[0]['MAX(lab_code)'])!='' && $labVlResult[0]['MAX(lab_code)']!=NULL){
    //     $_POST['labNo'] = $labVlResult[0]['MAX(lab_code)']+1;
    //  }else{
    //     $_POST['labNo'] = 1;
    //  }

    //Set Date of demand
    if (isset($_POST['dateOfDemand']) && trim($_POST['dateOfDemand']) != "") {
        $_POST['dateOfDemand'] = $general->dateFormat($_POST['dateOfDemand']);
    } else {
        $_POST['dateOfDemand'] = NULL;
    }
    //Set dob
    if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
        $_POST['dob'] = $general->dateFormat($_POST['dob']);
    } else {
        $_POST['dob'] = NULL;
    }
    //Set is patient new
    if (!isset($_POST['isPatientNew']) || trim($_POST['isPatientNew']) == '') {
        $_POST['isPatientNew'] = NULL;
        $_POST['dateOfArtInitiation'] = NULL;
    } else if ($_POST['isPatientNew'] == "yes") {
        //Ser ARV initiation date
        if (isset($_POST['dateOfArtInitiation']) && trim($_POST['dateOfArtInitiation']) != "") {
            $_POST['dateOfArtInitiation'] = $general->dateFormat($_POST['dateOfArtInitiation']);
        } else {
            $_POST['dateOfArtInitiation'] = NULL;
        }
    } else if ($_POST['isPatientNew'] == "no") {
        $_POST['dateOfArtInitiation'] = NULL;
    }
    //Set gender/it's realted values
    if (!isset($_POST['gender']) || trim($_POST['gender']) == '') {
        $_POST['gender'] = NULL;
        $_POST['breastfeeding'] = NULL;
        $_POST['patientPregnant'] = NULL;
        $_POST['trimester'] = NULL;
    } else if ($_POST['gender'] == "female") {
        if (!isset($_POST['breastfeeding']) || trim($_POST['breastfeeding']) == "") {
            $_POST['breastfeeding'] = NULL;
        }
        if (!isset($_POST['patientPregnant']) || trim($_POST['patientPregnant']) == "") {
            $_POST['patientPregnant'] = NULL;
        }
        if (!isset($_POST['trimester']) || trim($_POST['trimester']) == "") {
            $_POST['trimester'] = NULL;
        }
    } else if ($_POST['gender'] == "male") {
        $_POST['breastfeeding'] = NULL;
        $_POST['patientPregnant'] = NULL;
        $_POST['trimester'] = NULL;
    }

    //Set ARV current regimen
    if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $data = array(
            'art_code' => $_POST['newArtRegimen'],
            'parent_art' => 3,
            'updated_datetime' => $general->getDateTime(),
        );

        $result = $db->insert('r_vl_art_regimen', $data);
        $_POST['artRegimen'] = $_POST['newArtRegimen'];
    }
    //Regimen change section
    if (!isset($_POST['hasChangedRegimen']) || trim($_POST['hasChangedRegimen']) == '') {
        $_POST['hasChangedRegimen'] = NULL;
        $_POST['reasonForArvRegimenChange'] = NULL;
        $_POST['dateOfArvRegimenChange'] = NULL;
    }
    if (trim($_POST['hasChangedRegimen']) == "no") {
        $_POST['reasonForArvRegimenChange'] = NULL;
        $_POST['dateOfArvRegimenChange'] = NULL;
    } else if (trim($_POST['hasChangedRegimen']) == "yes") {
        if (isset($_POST['dateOfArvRegimenChange']) && trim($_POST['dateOfArvRegimenChange']) != "") {
            $_POST['dateOfArvRegimenChange'] = $general->dateFormat($_POST['dateOfArvRegimenChange']);
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
                $_POST['vlTestReason'] = NULL;
            }
        }
    } else {
        $_POST['vlTestReason'] = NULL;
    }
    //Set Viral load no.
    if (!isset($_POST['viralLoadNo']) || trim($_POST['viralLoadNo']) == '') {
        $_POST['viralLoadNo'] = NULL;
    }
    //Set last VL test date
    if (isset($_POST['lastViralLoadTestDate']) && trim($_POST['lastViralLoadTestDate']) != "") {
        $_POST['lastViralLoadTestDate'] = $general->dateFormat($_POST['lastViralLoadTestDate']);
    } else {
        $_POST['lastViralLoadTestDate'] = NULL;
    }
    //Set sample collection date
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleCollectionDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = $general->dateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
    } else {
        $_POST['sampleCollectionDate'] = NULL;
    }
    //Sample type section
    if (isset($_POST['specimenType']) && trim($_POST['specimenType']) != "") {
        if (trim($_POST['specimenType']) != 2) {
            $_POST['conservationTemperature'] = NULL;
            $_POST['durationOfConservation'] = NULL;
        }
    } else {
        $_POST['specimenType'] = NULL;
        $_POST['conservationTemperature'] = NULL;
        $_POST['durationOfConservation'] = NULL;
    }
    //Set sample received date
    if (isset($_POST['sampleReceivedDate']) && trim($_POST['sampleReceivedDate']) != "") {
        $sampleReceivedDate = explode(" ", $_POST['sampleReceivedDate']);
        $_POST['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
    } else {
        $_POST['sampleReceivedDate'] = NULL;
    }
    //Set sample rejection reason
    if (isset($_POST['status']) && trim($_POST['status']) != '') {
        if ($_POST['status'] == 4) {
            if (trim($_POST['rejectionReason']) == "other" && trim($_POST['newRejectionReason'] != '')) {
                $data = array(
                    'rejection_reason_name' => $_POST['newRejectionReason'],
                    'rejection_reason_status' => 'active',
                    'updated_datetime' => $general->getDateTime(),
                );
                $id = $db->insert('r_vl_sample_rejection_reasons', $data);
                $_POST['rejectionReason'] = $id;
            } else {
                $_POST['rejectionReason'] = NULL;
            }
        } else {
            $_POST['rejectionReason'] = NULL;
        }
    } else {
        $_POST['status'] = 6;
        $_POST['rejectionReason'] = NULL;
    }
    if ($_SESSION['instanceType'] == 'remoteuser') {
        $_POST['status'] = 9;
    }
    //Set result prinetd date time
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestedDate = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = NULL;
    }
    //Set Dispatched From Clinic To Lab Date
    if (isset($_POST['dateDispatchedFromClinicToLab']) && trim($_POST['dateDispatchedFromClinicToLab']) != "") {
        $dispatchedFromClinicToLabDate = explode(" ", $_POST['dateDispatchedFromClinicToLab']);
        $_POST['dateDispatchedFromClinicToLab'] = $general->dateFormat($dispatchedFromClinicToLabDate[0]) . " " . $dispatchedFromClinicToLabDate[1];
    } else {
        $_POST['dateDispatchedFromClinicToLab'] = NULL;
    }
    //Set sample testing date
    if (isset($_POST['dateOfCompletionOfViralLoad']) && trim($_POST['dateOfCompletionOfViralLoad']) != "") {
        $dateofCompletionofViralLoad = explode(" ", $_POST['dateOfCompletionOfViralLoad']);
        $_POST['dateOfCompletionOfViralLoad'] = $general->dateFormat($dateofCompletionofViralLoad[0]) . " " . $dateofCompletionofViralLoad[1];
    } else {
        $_POST['dateOfCompletionOfViralLoad'] = NULL;
    }
    if (!isset($_POST['sampleCode']) || trim($_POST['sampleCode']) == '') {
        $_POST['sampleCode'] = NULL;
    }
    $testingPlatform = NULL;
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



    $textResult =  null;
    if (isset($_POST['vlTND']) && $_POST['vlTND'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = 'Target not Detected';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt20']) && $_POST['vlLt20'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 20';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt40']) && $_POST['vlLt40'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 40';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['vlLt400']) && $_POST['vlLt400'] == 'yes' && $_POST['rejectionReason'] == NULL) {
        $textResult = $_POST['vlResult'] = '< 400';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }

    if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
		$approvedOn = explode(" ", $_POST['approvedOn']);
		$_POST['approvedOn'] = $general->dateFormat($approvedOn[0]) . " " . $approvedOn[1];
	} else {
		$_POST['approvedOn'] = NULL;
	}
    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'vlsm_country_id' => 3,
        //'sample_code_title'=>(isset($_POST['sampleCodeTitle']) && $_POST['sampleCodeTitle']!='' ? $_POST['sampleCodeTitle'] :  'auto'),

        'serial_no' => (isset($_POST['serialNo']) && $_POST['serialNo'] != '' ? $_POST['serialNo'] :  NULL),
        'facility_id' => $_POST['clinicName'],
        'province_id' => (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] :  NULL,
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
        'result_status' => $_POST['status'],
        'reason_for_sample_rejection' => $_POST['rejectionReason'],
        //'sample_code'=>$_POST['sampleCode'],
        //'lab_code'=>$_POST['labNo'],
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] :  NULL),
        'sample_tested_datetime' => $_POST['dateOfCompletionOfViralLoad'],
        'vl_test_platform' => $testingPlatform,
        'result_value_log' => (isset($_POST['vlLog'])) ? $_POST['vlLog'] : NULL,
        'result' => (isset($_POST['vlResult'])) ? $_POST['vlResult'] : NULL,
        'result_value_text' => $textResult,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' 	   => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
		'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  NULL,
        'date_test_ordered_by_physician' => $_POST['dateOfDemand'],
        'funding_source' => (isset($_POST['fundingSource']) && trim($_POST['fundingSource']) != '') ? base64_decode($_POST['fundingSource']) : NULL,
        'implementing_partner' => (isset($_POST['implementingPartner']) && trim($_POST['implementingPartner']) != '') ? base64_decode($_POST['implementingPartner']) : NULL,
        'vl_test_number' => $_POST['viralLoadNo'],
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        'date_dispatched_from_clinic_to_lab' => $_POST['dateDispatchedFromClinicToLab'],
        //'result_printed_datetime'=>$_POST['sampleTestingDateAtLab'],
        'request_created_by' => $_SESSION['userId'],
        'request_created_datetime' => $general->getDateTime(),
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime(),
        'vl_result_category' => $vl_result_category
    );
    // print_r($vldata);die;
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    if ($_POST['status'] == 7 && $lock == 'yes') {
        $vldata['locked'] = 'yes';
    }
    /* Updating the high and low viral load data */
    if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
        $vlDb = new \Vlsm\Models\Vl();
        $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
    }
    if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
        $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
        $id = $db->update($tableName, $vldata);
    } else {
        //check existing sample code
        $existSampleQuery = "SELECT " . $sampleCode . "," . $sampleCodeKey . " FROM vl_request_form where " . $sampleCode . " ='" . trim($_POST['sampleCode']) . "'";
        $existResult = $db->rawQuery($existSampleQuery);
        if (isset($existResult[0][$sampleCodeKey]) && $existResult[0][$sampleCodeKey] != '') {
            if ($existResult[0][$sampleCodeKey] != '') {
                $sCode = $existResult[0][$sampleCodeKey] + 1;
                $strparam = strlen($sCode);
                $zeros = substr("000", $strparam);
                $maxId = $zeros . $sCode;
                $_POST['sampleCode'] = $_POST['sampleCodeFormat'] . $maxId;
                $_POST['sampleCodeKey'] = $maxId;
            } else {
                $_SESSION['alertMsg'] = "Please check your sample ID";
                header("location:addVlRequest.php");
            }
        }

        if ($_SESSION['instanceType'] == 'remoteuser') {
            $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  NULL;
            $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  NULL;
            $vldata['remote_sample'] = 'yes';
        } else {
            $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  NULL;
            $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  NULL;
            $vldata['sample_registered_at_lab'] = $general->getDateTime();
        }
        $vldata['sample_code_format'] = (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '' ? $_POST['sampleCodeFormat'] :  NULL);
        $id = $db->insert($tableName, $vldata);
    }

    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL request added successfully";
        //Add event log
        $eventType = 'add-vl-request-drc';
        $action = ucwords($_SESSION['userName']) . ' added a new request data with the patient code ' . $_POST['patientArtNo'];
        $resource = 'vl-request-drc';

        $general->activityLog($eventType, $action, $resource);

        // $data=array(
        // 'event_type'=>$eventType,
        // 'action'=>$action,
        // 'resource'=>$resource,
        // 'date_time'=>$general->getDateTime()
        // );
        // $db->insert($tableName1,$data);

    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("location:vlRequest.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
