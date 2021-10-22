<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');

//echo "<pre>";var_dump($_POST);die;
$general = new \Vlsm\Models\General($db);
$tableName = "vl_request_form";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$fDetails = "facility_details";
$vl_result_category = NULL;
try {
    $validateField = array($_POST['sampleCode'], $_POST['sampleCollectionDate']);
    $chkValidation = $general->checkMandatoryFields($validateField);
    if ($chkValidation) {
        $_SESSION['alertMsg'] = "Please enter all mandatory fields to save the test request";
        header("location:editVlRequest.php?id=" . base64_encode($_POST['vlSampleId']));
        die;
    }
    $configQuery = "SELECT * from global_config";
    $configResult = $db->query($configQuery);
    $arr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
        $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    if ($sarr['sc_user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
        $_POST['status'] = 9;
    } else if ($_POST['oldStatus'] == 9) {
        $_POST['status'] = 6;
    }
    if ($_POST['status'] == '') {
        $_POST['status']  = $_POST['oldStatus'];
    }
    //add province
    $splitProvince = explode("##", $_POST['province']);
    if (isset($splitProvince[0]) && trim($splitProvince[0]) != '') {
        $provinceQuery = "SELECT * from province_details where province_name='" . $splitProvince[0] . "'";
        $provinceInfo = $db->query($provinceQuery);
        if (!isset($provinceInfo) || count($provinceInfo) == 0) {
            $db->insert('province_details', array('province_name' => $splitProvince[0], 'province_code' => $splitProvince[1]));
        }
    }
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != "") {
        $sampleDate = explode(" ", $_POST['sampleCollectionDate']);
        $_POST['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
    } else {
        $_POST['sampleCollectionDate'] = NULL;
    }

    if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
        $_POST['dob'] = $general->dateFormat($_POST['dob']);
    } else {
        $_POST['dob'] = NULL;
    }

    if (isset($_POST['dateOfArtInitiation']) && trim($_POST['dateOfArtInitiation']) != "") {
        $_POST['dateOfArtInitiation'] = $general->dateFormat($_POST['dateOfArtInitiation']);
    } else {
        $_POST['dateOfArtInitiation'] = NULL;
    }

    if (isset($_POST['regimenInitiatedOn']) && trim($_POST['regimenInitiatedOn']) != "") {
        $_POST['regimenInitiatedOn'] = $general->dateFormat($_POST['regimenInitiatedOn']);
    } else {
        $_POST['regimenInitiatedOn'] = NULL;
    }

    if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
        $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen where (art_code='" . $_POST['newArtRegimen'] . "' OR art_code='" . strtolower($_POST['newArtRegimen']) . "' OR art_code='" . ucfirst(strtolower($_POST['newArtRegimen'])) . "')";
        $artResult = $db->rawQuery($artQuery);
        if (!isset($artResult[0]['art_id'])) {
            $data = array(
                'art_code' => $_POST['newArtRegimen'],
                'parent_art' => '7',
                'updated_datetime' => $general->getDateTime(),
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
        $_POST['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
    } else {
        $_POST['sampleReceivedDate'] = NULL;
    }
    if (isset($_POST['sampleTestingDateAtLab']) && trim($_POST['sampleTestingDateAtLab']) != "") {
        $sampleTestingDateAtLab = explode(" ", $_POST['sampleTestingDateAtLab']);
        $_POST['sampleTestingDateAtLab'] = $general->dateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
    } else {
        $_POST['sampleTestingDateAtLab'] = NULL;
    }
    if (isset($_POST['resultDispatchedOn']) && trim($_POST['resultDispatchedOn']) != "") {
        $resultDispatchedOn = explode(" ", $_POST['resultDispatchedOn']);
        $_POST['resultDispatchedOn'] = $general->dateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
    } else {
        $_POST['resultDispatchedOn'] = NULL;
    }

    if (isset($_POST['newRejectionReason']) && trim($_POST['newRejectionReason']) != "") {
        $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_vl_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower($_POST['newRejectionReason']) . "' OR rejection_reason_name='" . ucfirst(strtolower($_POST['newRejectionReason'])) . "'";
        $rejectionResult = $db->rawQuery($rejectionReasonQuery);
        if (!isset($rejectionResult[0]['rejection_reason_id'])) {
            $data = array(
                'rejection_reason_name' => $_POST['newRejectionReason'],
                'rejection_type' => 'general',
                'rejection_reason_status' => 'active',
                'updated_datetime' => $general->getDateTime(),
            );
            $id = $db->insert('r_vl_sample_rejection_reasons', $data);
            $_POST['rejectionReason'] = $id;
        } else {
            $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
        }
    }

    $isRejection = false;
    if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
        $vl_result_category = 'rejected';
        $isRejection = true;
        $_POST['vlResult'] = '';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Target Not Detected';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['lt20']) && $_POST['lt20'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = '<20';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['lt40']) && $_POST['lt40'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = '<40';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['bdl']) && $_POST['bdl'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Below Detection Level';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['failed']) && $_POST['failed'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Failed';
        $_POST['vlLog'] = '';
    }
    if (isset($_POST['invalid']) && $_POST['invalid'] == 'yes' && $isRejection == false) {
        $_POST['vlResult'] = 'Invalid';
        $_POST['vlLog'] = '';
    }

    if (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') {
        $_POST['result'] = $_POST['vlResult'];
    } else if (isset($_POST['vlLog']) && trim($_POST['vlLog']) != '') {
        $_POST['result_value_log'] = $_POST['vlLog'];
    }



    $reasonForChanges = '';
    $allChange = '';
    if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
        $allChange = $_POST['reasonForResultChangesHistory'];
    }
    if (isset($_POST['reasonForResultChanges']) && trim($_POST['reasonForResultChanges']) != '') {
        $reasonForChanges = $_SESSION['userName'] . '##' . $_POST['reasonForResultChanges'] . '##' . $general->getDateTime();
    }
    if (trim($allChange) != '' && trim($reasonForChanges) != '') {
        $allChange = $reasonForChanges . 'vlsm' . $allChange;
    } else if (trim($reasonForChanges) != '') {
        $allChange =  $reasonForChanges;
    }
    //Indication for Viral Load Testing
    $rmVLValue = NULL;
    if (isset($_POST['stViralTesting']) && $_POST['stViralTesting'] == 'routine') {
        if (isset($_POST['rmTestingVlValue']) && $_POST['rmTestingVlValue'] != '') {
            $rmVLValue = $_POST['rmTestingVlValue'];
        } else if (isset($_POST['rmTestingVlCheckValue']) && $_POST['rmTestingVlCheckValue'] != '') {
            $rmVLValue = $_POST['rmTestingVlCheckValue'];
        }
    }
    $repeatTestingVLValue = NULL;
    if (isset($_POST['stViralTesting']) && $_POST['stViralTesting'] == 'failure') {
        if (isset($_POST['repeatTestingVlValue']) && $_POST['repeatTestingVlValue'] != '') {
            $repeatTestingVLValue = $_POST['repeatTestingVlValue'];
        } else if (isset($_POST['repeatTestingVlCheckValue']) && $_POST['repeatTestingVlCheckValue'] != '') {
            $repeatTestingVLValue = $_POST['repeatTestingVlCheckValue'];
        }
    }
    $suspendedTreatmentVLValue = NULL;
    if (isset($_POST['stViralTesting']) && $_POST['stViralTesting'] == 'suspect') {
        if (isset($_POST['suspendTreatmentVlValue']) && $_POST['suspendTreatmentVlValue'] != '') {
            $suspendedTreatmentVLValue = $_POST['suspendTreatmentVlValue'];
        } else if (isset($_POST['suspendTreatmentVlCheckValue']) && $_POST['suspendTreatmentVlCheckValue'] != '') {
            $suspendedTreatmentVLValue = $_POST['suspendTreatmentVlCheckValue'];
        }
    }

    //set vl test reason
    if (isset($_POST['stViralTesting']) && trim($_POST['stViralTesting']) != "") {
        $reasonQuery = "SELECT test_reason_id FROM r_vl_test_reasons where test_reason_name='" . $_POST['stViralTesting'] . "'";
        $reasonResult = $db->rawQuery($reasonQuery);
        if (isset($reasonResult[0]['test_reason_id']) && $reasonResult[0]['test_reason_id'] != '') {
            $_POST['stViralTesting'] = $reasonResult[0]['test_reason_id'];
        } else {
            $data = array(
                'test_reason_name' => $_POST['stViralTesting'],
                'test_reason_status' => 'active'
            );
            $id = $db->insert('r_vl_test_reasons', $data);
            $_POST['stViralTesting'] = $id;
        }
    }

    if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
        $reviewedOn = explode(" ", $_POST['reviewedOn']);
        $_POST['reviewedOn'] = $general->dateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
    } else {
        $_POST['reviewedOn'] = NULL;
    }

    $vldata = array(
        'vlsm_instance_id' => $instanceId,
        'sample_reordered' => (isset($_POST['sampleReordered']) && $_POST['sampleReordered'] != '') ? $_POST['sampleReordered'] : 'no',
        //'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='') ? $_POST['sampleCode'] :  NULL,
        'facility_id' => (isset($_POST['fName']) && $_POST['fName'] != '') ? $_POST['fName'] : NULL,
        'sample_collection_date' => $_POST['sampleCollectionDate'],
        //'patient_first_name'=>(isset($_POST['patientFirstName']) && $_POST['patientFirstName']!='') ? $_POST['patientFirstName'] :  NULL,
        'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] : NULL,
        'patient_dob' => $_POST['dob'],
        'patient_age_in_years' => (isset($_POST['ageInYears']) && $_POST['ageInYears'] != '') ? $_POST['ageInYears'] : NULL,
        'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] : NULL,
        'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] : NULL,
        'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] : NULL,
        'patient_art_no' => (isset($_POST['artNo']) && $_POST['artNo'] != '') ? $_POST['artNo'] : NULL,
        'treatment_initiated_date' => $_POST['dateOfArtInitiation'],
        'current_regimen' => (isset($_POST['artRegimen']) && $_POST['artRegimen'] != '') ? $_POST['artRegimen'] : NULL,
        'date_of_initiation_of_current_regimen' => $_POST['regimenInitiatedOn'],
        'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] : NULL,
        'sample_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] : NULL,
        'arv_adherance_percentage' => (isset($_POST['arvAdherence']) && $_POST['arvAdherence'] != '') ? $_POST['arvAdherence'] : NULL,
        'reason_for_vl_testing' => (isset($_POST['stViralTesting'])) ? $_POST['stViralTesting'] : NULL,
        'last_vl_date_routine' => (isset($_POST['rmTestingLastVLDate']) && $_POST['rmTestingLastVLDate'] != '') ? $general->dateFormat($_POST['rmTestingLastVLDate']) : NULL,
        'last_vl_result_routine' => $rmVLValue,
        'last_vl_date_failure_ac' => (isset($_POST['repeatTestingLastVLDate']) && $_POST['repeatTestingLastVLDate'] != '') ? $general->dateFormat($_POST['repeatTestingLastVLDate']) : NULL,
        'last_vl_result_failure_ac' => $repeatTestingVLValue,
        'last_vl_date_failure' => (isset($_POST['suspendTreatmentLastVLDate']) && $_POST['suspendTreatmentLastVLDate'] != '') ? $general->dateFormat($_POST['suspendTreatmentLastVLDate']) : NULL,
        'last_vl_result_failure' => $suspendedTreatmentVLValue,
        'request_clinician_name' => (isset($_POST['reqClinician']) && $_POST['reqClinician'] != '') ? $_POST['reqClinician'] : NULL,
        'request_clinician_phone_number' => (isset($_POST['reqClinicianPhoneNumber']) && $_POST['reqClinicianPhoneNumber'] != '') ? $_POST['reqClinicianPhoneNumber'] : NULL,
        'test_requested_on' => (isset($_POST['requestDate']) && $_POST['requestDate'] != '') ? $general->dateFormat($_POST['requestDate']) : NULL,
        'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] : NULL,
        'vl_focal_person_phone_number' => (isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber'] != '') ? $_POST['vlFocalPersonPhoneNumber'] : NULL,
        'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] : NULL,
        'vl_test_platform' => $testingPlatform,
        //'test_methods'=>(isset($_POST['testMethods']) && $_POST['testMethods']!='') ? $_POST['testMethods'] :  NULL,
        'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
        'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
        'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
        'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] : NULL,
        'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : NULL,
        'result_value_log' => (isset($_POST['vlLog']) && $_POST['vlLog'] != '') ? $_POST['vlLog'] : NULL,
        'result_value_absolute' => !empty($_POST['vlResult']) ? $_POST['vlResult'] : NULL,
        'result_value_text' => NULL,
        'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected'  && $_POST['vlResult'] != 'Below Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') : NULL,
        'result' => (isset($_POST['result']) && $_POST['result'] != '') ? $_POST['result'] : NULL,
        'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
        'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
        'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : NULL,
        'approver_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) : NULL,
        'result_status' => (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] : NULL,
        'reason_for_vl_result_changes' => $allChange,
        'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
        'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getDateTime() : "",
        'last_modified_by' => $_SESSION['userId'],
        'last_modified_datetime' => $general->getDateTime(),
        'manual_result_entry' => 'yes',
        'data_sync' => 0,
        'vl_result_category' => $vl_result_category
    );
    /* Updating the high and low viral load data */
    if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
        $vlDb = new \Vlsm\Models\Vl($db);
        $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
    }
    $lock = $general->getGlobalConfig('lock_approved_vl_samples');
    if ($_POST['status'] == 7 && $lock == 'yes') {
        $vldata['locked'] = 'yes';
    }
    if ($sarr['sc_user_type'] == 'remoteuser') {
        $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
    } else {
        if ($_POST['sampleCodeCol'] != '') {
            $vldata['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : NULL;
        } else {
            //Since Sample Code does not exist, today is the date
            //sample is being registered at the lab.
            $vldata['sample_registered_at_lab'] = $general->getDateTime();

            //update sample code generation
            $sExpDT = explode(" ", $_POST['sampleCollectionDate']);
            $sExpDate = explode("-", $sExpDT[0]);
            $start_date = date($sExpDate[0] . '-01-01') . " " . '00:00:00';
            $end_date = date($sExpDate[0] . '-12-31') . " " . '23:59:59';
            $mnthYr = substr($sExpDate[0], -2);
            if ($arr['sample_code'] == 'MMYY') {
                $mnthYr = $sExpDate[1] . substr($sExpDate[0], -2);
            } else if ($arr['sample_code'] == 'YY') {
                $mnthYr = substr($sExpDate[0], -2);
            }
            $auto = substr($sExpDate[0], -2) . $sExpDate[1] . $sExpDate[2];

            $svlQuery = 'SELECT sample_code_key FROM vl_request_form as vl WHERE DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '" AND sample_code!="" ORDER BY sample_code_key DESC LIMIT 1';
            $svlResult = $db->query($svlQuery);
            $prefix = $arr['sample_code_prefix'];
            if (isset($svlResult[0]['sample_code_key']) && $svlResult[0]['sample_code_key'] != '' && $svlResult[0]['sample_code_key'] != NULL) {
                $maxId = $svlResult[0]['sample_code_key'] + 1;
                $strparam = strlen($maxId);
                $zeros = substr("000", $strparam);
                $maxId = $zeros . $maxId;
            } else {
                $maxId = '001';
            }
            if ($arr['sample_code'] == 'auto') {
                $vldata['sample_code'] = $auto . $maxId;
                $vldata['sample_code_key'] = $maxId;
            } else if ($arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') {
                $vldata['sample_code'] = $prefix . $mnthYr . $maxId;
                $vldata['sample_code_format'] = $prefix . $mnthYr;
                $vldata['sample_code_key'] =  $maxId;
            }
        }
    }
    $vldata['patient_first_name'] = $general->crypto('encrypt', $_POST['patientFirstName'], $vldata['patient_art_no']);

    //var_dump($vldata);die;

    $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
    $id = $db->update($tableName, $vldata);

    if ($id > 0) {
        $_SESSION['alertMsg'] = "VL request updated successfully";
        //Add event log
        $eventType = 'update-vl-request-rwd';
        $action = ucwords($_SESSION['userName']) . ' updated a request data with the sample code ' . $_POST['sampleCode'];
        $resource = 'vl-request-rwd';

        $general->activityLog($eventType, $action, $resource);

        //  $data=array(
        //  'event_type'=>$eventType,
        //  'action'=>$action,
        //  'resource'=>$resource,
        //  'date_time'=>$general->getDateTime()
        //  );
        //  $db->insert($tableName1,$data);

    } else {
        $_SESSION['alertMsg'] = "Please try again later";
    }
    header("location:vlRequest.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
