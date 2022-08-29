<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
ob_start();


$general = new \Vlsm\Models\General();
$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$vl_result_category = NULL;
try {
     $validateField = array($_POST['sampleCode'], $_POST['collectionDate']);
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

     if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
          $_POST['dob'] = $general->isoDateFormat($_POST['dob']);
     } else {
          $_POST['dob'] = NULL;
     }

     if (isset($_POST['collectionDate']) && trim($_POST['collectionDate']) != "") {
          $sampleDate = explode(" ", $_POST['collectionDate']);
          $_POST['collectionDate'] = $general->isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];
     } else {
          $_POST['collectionDate'] = NULL;
     }
     if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
          $failedtestDate = explode(" ", $_POST['failedTestDate']);
          $_POST['failedTestDate'] = $general->isoDateFormat($failedtestDate[0]) . " " . $failedtestDate[1];
     } else {
          $_POST['failedTestDate'] = NULL;
     }

     if (isset($_POST['regStartDate']) && trim($_POST['regStartDate']) != "") {
          $_POST['regStartDate'] = $general->isoDateFormat($_POST['regStartDate']);
     } else {
          $_POST['regStartDate'] = NULL;
     }

     if (isset($_POST['receivedDate']) && trim($_POST['receivedDate']) != "") {
          $sampleReceivedDate = explode(" ", $_POST['receivedDate']);
          $_POST['receivedDate'] = $general->isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
     } else {
          $_POST['receivedDate'] = NULL;
     }
     if (isset($_POST['testDate']) && trim($_POST['testDate']) != "") {
          $sampletestDate = explode(" ", $_POST['testDate']);
          $_POST['testDate'] = $general->isoDateFormat($sampletestDate[0]) . " " . $sampletestDate[1];
     } else {
          $_POST['testDate'] = NULL;
     }
     if (isset($_POST['cdDate']) && trim($_POST['cdDate']) != "") {
          $_POST['cdDate'] = $general->isoDateFormat($_POST['cdDate']);
     } else {
          $_POST['cdDate'] = NULL;
     }
     if (isset($_POST['qcDate']) && trim($_POST['qcDate']) != "") {
          $_POST['qcDate'] = $general->isoDateFormat($_POST['qcDate']);
     } else {
          $_POST['qcDate'] = NULL;
     }
     if (isset($_POST['reportDate']) && trim($_POST['reportDate']) != "") {
          $_POST['reportDate'] = $general->isoDateFormat($_POST['reportDate']);
     } else {
          $_POST['reportDate'] = NULL;
     }
     if (isset($_POST['clinicDate']) && trim($_POST['clinicDate']) != "") {
          $_POST['clinicDate'] = $general->isoDateFormat($_POST['clinicDate']);
     } else {
          $_POST['clinicDate'] = NULL;
     }

     if ($_POST['testingTech'] != '') {
          $platForm = explode("##", $_POST['testingTech']);
          $_POST['testingTech'] = $platForm[0];
     }
     if ($_POST['failedTestingTech'] != '') {
          $platForm = explode("##", $_POST['failedTestingTech']);
          $_POST['failedTestingTech'] = $platForm[0];
     }
     if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
          $data = array(
               'art_code' => $_POST['newArtRegimen'],
               'parent_art' => '5'
          );
          $result = $db->insert('r_vl_art_regimen', $data);
          $_POST['currentRegimen'] = $_POST['newArtRegimen'];
     }
     if (isset($_POST['sampleQuality']) && trim($_POST['sampleQuality']) == 'no') {
          $_POST['rejectionReason'] = NULL;
     }
     if (isset($_POST['sampleQuality']) && trim($_POST['sampleQuality']) == 'yes') {
          $vl_result_category = 'rejected';
          $_POST['vlResult'] = NULL;
     }

     $reasonForTestField = NULL;
     if (isset($_POST['reasonForTest']) && $_POST['reasonForTest'] != '') {

          $reasonQuery = "SELECT test_reason_id FROM r_vl_test_reasons where test_reason_name='" . $_POST['reasonForTest'] . "'";
          $reasonResult = $db->rawQuery($reasonQuery);
          if (isset($reasonResult[0]['test_reason_id']) && $reasonResult[0]['test_reason_id'] != '') {
               $reasonForTestField = $reasonResult[0]['test_reason_id'];
          } else {
               $data = array(
                    'test_reason_name' => $_POST['reasonForTest'],
                    'test_reason_status' => 'active'
               );
               $id = $db->insert('r_vl_test_reasons', $data);
               $reasonForTestField = $id;
          }
     }



     if ($sarr['sc_user_type'] == 'remoteuser' && $_POST['oldStatus'] == 9) {
          $_POST['status'] = 9;
     } else if ($sarr['sc_user_type'] == 'vluser' && $_POST['oldStatus'] == 9) {
          $_POST['status'] = 6;
     }

     if (empty($_POST['status'])) {
          $_POST['status']  = $_POST['oldStatus'];
     }

     if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
          $reviewedOn = explode(" ", $_POST['reviewedOn']);
          $_POST['reviewedOn'] = $general->isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
     } else {
          $_POST['reviewedOn'] = NULL;
     }
     if (isset($_POST['approvedOn']) && trim($_POST['approvedOn']) != "") {
		$approvedOn = explode(" ", $_POST['approvedOn']);
		$_POST['approvedOn'] = $general->isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
	} else {
		$_POST['approvedOn'] = NULL;
	}

     $vldata = array(
          //'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='') ? $_POST['sampleCode'] :  NULL,
          'facility_id' => (isset($_POST['clinicName']) && trim($_POST['clinicName']) != '') ? $_POST['clinicName'] : NULL,
          //'ward'=>(isset($_POST['wardData']) && $_POST['wardData']!='' ? $_POST['wardData'] :  NULL),
          'patient_art_no' => (isset($_POST['patientARTNo']) && trim($_POST['patientARTNo']) != '') ? $_POST['patientARTNo'] : NULL,
          'request_clinician_name' => (isset($_POST['officerName']) && $_POST['officerName'] != '' ? $_POST['officerName'] : NULL),
          'lab_phone_number' => (isset($_POST['telephone']) && $_POST['telephone'] != '' ? $_POST['telephone'] : NULL),
          //'patient_first_name'=>(isset($_POST['patientFname']) && $_POST['patientFname']!='' ? $_POST['patientFname'] :  NULL),
          //'patient_last_name'=>(isset($_POST['surName']) && $_POST['surName']!='' ? $_POST['surName'] :  NULL),
          'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '' ? $_POST['gender'] : NULL),
          'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] : NULL,
          'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] : NULL,
          'patient_dob' => $_POST['dob'],
          'patient_age_in_years' => (isset($_POST['ageInYears']) && $_POST['ageInYears'] != '') ? $_POST['ageInYears'] : NULL,
          'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] : NULL,
          'line_of_treatment' => (isset($_POST['artLine']) && $_POST['artLine'] != '') ? $_POST['artLine'] : NULL,
          'current_regimen' => (isset($_POST['currentRegimen']) && $_POST['currentRegimen'] != '') ? $_POST['currentRegimen'] : NULL,
          'date_of_initiation_of_current_regimen' => $_POST['regStartDate'],
          'art_cd_cells' => (isset($_POST['cdCells']) && $_POST['cdCells'] != '' ? $_POST['cdCells'] : NULL),
          'art_cd_date' => $_POST['cdDate'],
          'who_clinical_stage' => (isset($_POST['clinicalStage']) && $_POST['clinicalStage'] != '' ? $_POST['clinicalStage'] : NULL),
          //'reason_testing_png'=>(isset($_POST['reasonForTest']) && $_POST['reasonForTest']!='' ? $_POST['reasonForTest'] :  NULL),
          'reason_for_vl_testing' => $reasonForTestField,
          'reason_for_vl_testing_other' => (isset($_POST['reason']) && $_POST['reason'] != '' ? $_POST['reason'] : NULL),
          'sample_to_transport' => (isset($_POST['typeOfSample']) && $_POST['typeOfSample'] != '' ? $_POST['typeOfSample'] : NULL),
          'whole_blood_ml' => (isset($_POST['wholeBloodOne']) && $_POST['wholeBloodOne'] != '' ? $_POST['wholeBloodOne'] : NULL),
          'whole_blood_vial' => (isset($_POST['wholeBloodTwo']) && $_POST['wholeBloodTwo'] != '' ? $_POST['wholeBloodTwo'] : NULL),
          'plasma_ml' => (isset($_POST['plasmaOne']) && $_POST['plasmaOne'] != '' ? $_POST['plasmaOne'] : NULL),
          'plasma_vial' => (isset($_POST['plasmaTwo']) && $_POST['plasmaTwo'] != '' ? $_POST['plasmaTwo'] : NULL),
          'plasma_process_time' => (isset($_POST['processTime']) && $_POST['processTime'] != '' ? $_POST['processTime'] : NULL),
          'plasma_process_tech' => (isset($_POST['processTech']) && $_POST['processTech'] != '' ? $_POST['processTech'] : NULL),
          'is_sample_rejected' => (isset($_POST['sampleQuality']) && $_POST['sampleQuality'] != '' ? $_POST['sampleQuality'] : NULL),
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '' ? $_POST['rejectionReason'] : NULL),
          'batch_quality' => (isset($_POST['batchQuality']) && $_POST['batchQuality'] != '' ? $_POST['batchQuality'] : NULL),
          'sample_test_quality' => (isset($_POST['testQuality']) && $_POST['testQuality'] != '' ? $_POST['testQuality'] : NULL),
          'sample_batch_id' => (isset($_POST['batchNo']) && $_POST['batchNo'] != '' ? $_POST['batchNo'] : NULL),
          'failed_test_date' => $_POST['failedTestDate'],
          'failed_test_tech' => (isset($_POST['failedTestingTech']) && $_POST['failedTestingTech'] != '') ? $_POST['failedTestingTech'] : NULL,
          'failed_vl_result' => (isset($_POST['failedvlResult']) && $_POST['failedvlResult'] != '' ? $_POST['failedvlResult'] : NULL),
          'failed_batch_quality' => (isset($_POST['failedbatchQuality']) && $_POST['failedbatchQuality'] != '' ? $_POST['failedbatchQuality'] : NULL),
          'failed_sample_test_quality' => (isset($_POST['failedtestQuality']) && $_POST['failedtestQuality'] != '' ? $_POST['failedtestQuality'] : NULL),
          'failed_batch_id' => (isset($_POST['failedbatchNo']) && $_POST['failedbatchNo'] != '' ? $_POST['failedbatchNo'] : NULL),
          'sample_collection_date' => $_POST['collectionDate'],
          'sample_collected_by' => (isset($_POST['collectedBy']) && $_POST['collectedBy'] != '' ? $_POST['collectedBy'] : NULL),
          'lab_id' => (isset($_POST['laboratoryId']) && $_POST['laboratoryId'] != '' ? (int) $_POST['laboratoryId'] : NULL),
          'sample_type' => (isset($_POST['sampleType']) && $_POST['sampleType'] != '' ? $_POST['sampleType'] : NULL),
          'sample_received_at_vl_lab_datetime' => $_POST['receivedDate'],
          'tech_name_png' => (isset($_POST['techName']) && trim($_POST['techName']) != '') ? $_POST['techName'] : NULL,
          'sample_tested_datetime' => (isset($_POST['testDate']) && $_POST['testDate'] != '' ? $_POST['testDate'] : NULL),
          //'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] :  NULL),
          'vl_test_platform' => (isset($_POST['testingTech']) && trim($_POST['testingTech']) != '') ? $_POST['testingTech'] : NULL,
          'cphl_vl_result' => (isset($_POST['cphlvlResult']) && $_POST['cphlvlResult'] != '' ? $_POST['cphlvlResult'] : NULL),
          'result' => (isset($_POST['finalViralResult']) && trim($_POST['finalViralResult']) != '') ? $_POST['finalViralResult'] : NULL,
          'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
          'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
          'result_approved_by' 	   => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
		'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  NULL,
          'result_status' => (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] : NULL,
          'qc_tech_name' => (isset($_POST['qcTechName']) && $_POST['qcTechName'] != '' ? $_POST['qcTechName'] : NULL),
          'qc_tech_sign' => (isset($_POST['qcTechSign']) && $_POST['qcTechSign'] != '' ? $_POST['qcTechSign'] : NULL),
          'qc_date' => $_POST['qcDate'],
          'clinic_date' => $_POST['clinicDate'],
          'report_date' => $_POST['reportDate'],
          'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : "",
          'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $general->getCurrentDateTime() : "",
          'last_modified_by' => $_SESSION['userId'],
          'last_modified_datetime' => $db->now(),
          'data_sync' => 0,
          'vl_result_category' => $vl_result_category
     );


     /* Updating the high and low viral load data */
     if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
          $vlDb = new \Vlsm\Models\Vl();
          $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
     }

     if ($_SESSION['instanceType'] == 'remoteuser') {
          $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
     } else {
          if ($_POST['sampleCodeCol'] != '') {
               $vldata['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : NULL;
          } else {
               //Since Sample Code does not exist, today is the date
               //sample is being registered at the lab.
               $vldata['sample_registered_at_lab'] = $general->getCurrentDateTime();
               $province = $_POST['province'];
               $province = explode("##", $province);

               $vlObj = new \Vlsm\Models\Vl();
               $sampleJson = $vlObj->generateVLSampleID($province[1], $_POST['collectionDate'], 'png');
               $sampleData = json_decode($sampleJson, true);
               $vldata['sample_code'] = $sampleData['sampleCode'];
               $vldata['sample_code_format'] = $sampleData['sampleCodeFormat'];
               $vldata['sample_code_key'] = $sampleData['sampleCodeKey'];
          }
     }



     $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
     $id = $db->update($tableName, $vldata);


     $_SESSION['alertMsg'] = "VL request updated successfully";
     //Add event log
     $eventType = 'update-vl-request-png';
     $action = ucwords($_SESSION['userName']) . ' updated a request data with the sample code ' . $_POST['sampleCode'];
     $resource = 'vl-request-png';

     $general->activityLog($eventType, $action, $resource);

     //  $data=array(
     //       'event_type'=>$eventType,
     //       'action'=>$action,
     //       'resource'=>$resource,
     //       'date_time'=>$general->getCurrentDateTime()
     //  );
     //  $db->insert($tableName1,$data);
     header("location:vlRequest.php");
} catch (Exception $exc) {
     error_log($exc->getMessage());
     error_log($exc->getTraceAsString());
}
