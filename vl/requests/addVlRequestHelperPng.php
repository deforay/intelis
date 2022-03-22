<?php
if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
ob_start();



$general = new \Vlsm\Models\General();
$tableName = "vl_request_form";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
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

     if (isset($_POST['dob']) && trim($_POST['dob']) != "") {
          $_POST['dob'] = $general->dateFormat($_POST['dob']);
     } else {
          $_POST['dob'] = NULL;
     }

     if (isset($_POST['collectionDate']) && trim($_POST['collectionDate']) != "") {
          $sampleDate = explode(" ", $_POST['collectionDate']);
          $_POST['collectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
     } else {
          $_POST['collectionDate'] = NULL;
     }
     if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
          $failedtestDate = explode(" ", $_POST['failedTestDate']);
          $_POST['failedTestDate'] = $general->dateFormat($failedtestDate[0]) . " " . $failedtestDate[1];
     } else {
          $_POST['failedTestDate'] = NULL;
     }

     if (isset($_POST['regStartDate']) && trim($_POST['regStartDate']) != "") {
          $_POST['regStartDate'] = $general->dateFormat($_POST['regStartDate']);
     } else {
          $_POST['regStartDate'] = NULL;
     }

     if (isset($_POST['receivedDate']) && trim($_POST['receivedDate']) != "") {
          $sampleReceivedDate = explode(" ", $_POST['receivedDate']);
          $_POST['receivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
     } else {
          $_POST['receivedDate'] = NULL;
     }
     if (isset($_POST['testDate']) && trim($_POST['testDate']) != "") {
          $sampletestDate = explode(" ", $_POST['testDate']);
          $_POST['testDate'] = $general->dateFormat($sampletestDate[0]) . " " . $sampletestDate[1];
     } else {
          $_POST['testDate'] = NULL;
     }
     if (isset($_POST['cdDate']) && trim($_POST['cdDate']) != "") {
          $_POST['cdDate'] = $general->dateFormat($_POST['cdDate']);
     } else {
          $_POST['cdDate'] = NULL;
     }
     if (isset($_POST['qcDate']) && trim($_POST['qcDate']) != "") {
          $_POST['qcDate'] = $general->dateFormat($_POST['qcDate']);
     } else {
          $_POST['qcDate'] = NULL;
     }
     if (isset($_POST['clinicDate']) && trim($_POST['clinicDate']) != "") {
          $_POST['clinicDate'] = $general->dateFormat($_POST['clinicDate']);
     } else {
          $_POST['clinicDate'] = NULL;
     }
     if (isset($_POST['reportDate']) && trim($_POST['reportDate']) != "") {
          $_POST['reportDate'] = $general->dateFormat($_POST['reportDate']);
     } else {
          $_POST['reportDate'] = NULL;
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

     $status = 6;
     if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
          $status = 9;
     }

     if (isset($_POST['sampleQuality']) && trim($_POST['sampleQuality']) == 'no') {
          $_POST['rejectionReason'] = NULL;
     }
     if (isset($_POST['sampleQuality']) && trim($_POST['sampleQuality']) == 'yes') {
          $vl_result_category = 'rejected';
          $_POST['vlResult'] = NULL;
          $status = 4;
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
     $instanceId = '';
     if (isset($_SESSION['instanceId'])) {
          $instanceId = $_SESSION['instanceId'];
     }
     $vldata = array(
          //'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='') ? $_POST['sampleCode'] :  NULL,
          //'sample_code_format'=>(isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat']!='')? $_POST['sampleCodeFormat'] :  NULL,
          //'sample_code_key'=>(isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey']!='')? $_POST['sampleCodeKey'] :  NULL,
          'vlsm_instance_id' => $instanceId,
          'vlsm_country_id' => '5',
          'facility_id' => (isset($_POST['clinicName']) && trim($_POST['clinicName']) != '') ? $_POST['clinicName'] : NULL,
          'province_id' => (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] :  NULL,
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
          'is_sample_rejected' => (isset($_POST['sampleQuality']) && $_POST['sampleQuality'] != '') ? $_POST['sampleQuality'] : NULL,
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : NULL,
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
          'lab_id' => (isset($_POST['laboratoryId']) && $_POST['laboratoryId'] != '' ? $_POST['laboratoryId'] : NULL),
          'sample_type' => (isset($_POST['sampleType']) && $_POST['sampleType'] != '' ? $_POST['sampleType'] : NULL),
          'sample_received_at_vl_lab_datetime' => $_POST['receivedDate'],
          'tech_name_png' => (isset($_POST['techName']) && $_POST['techName'] != '') ? $_POST['techName'] : NULL,
          'sample_tested_datetime' => (isset($_POST['testDate']) && $_POST['testDate'] != '' ? $_POST['testDate'] : NULL),
          //'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] :  NULL),
          'vl_test_platform' => (isset($_POST['testingTech']) && $_POST['testingTech'] != '') ? $_POST['testingTech'] : NULL,
          'cphl_vl_result' => (isset($_POST['cphlvlResult']) && $_POST['cphlvlResult'] != '' ? $_POST['cphlvlResult'] : NULL),
          'result' => (isset($_POST['finalViralResult']) && trim($_POST['finalViralResult']) != '') ? $_POST['finalViralResult'] : NULL,
          'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
          'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
          'result_approved_by'         => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
          'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  NULL,
          'qc_tech_name' => (isset($_POST['qcTechName']) && $_POST['qcTechName'] != '' ? $_POST['qcTechName'] : NULL),
          'qc_tech_sign' => (isset($_POST['qcTechSign']) && $_POST['qcTechSign'] != '' ? $_POST['qcTechSign'] : NULL),
          'qc_date' => $_POST['qcDate'],
          'clinic_date' => $_POST['clinicDate'],
          'report_date' => $_POST['reportDate'],
          'result_status' => $status,
          'request_created_by' => $_SESSION['userId'],
          'request_created_datetime' => $db->now(),
          'last_modified_by' => $_SESSION['userId'],
          'last_modified_datetime' => $db->now(),
          'data_sync' => 0,
          'vl_result_category' => $vl_result_category
     );

     if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "vluser" || $sarr['sc_user_type'] == "standalone")) {
          $vldata['source_of_request'] = 'vlsm';
     } else if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == "remoteuser")) {
          $vldata['source_of_request'] = 'vlsts';
     } else if (!empty($_POST['api']) && $_POST['api'] = "yes") {
          $vldata['source_of_request'] = 'api';
     }

     //$vldata['patient_first_name'] = $general->crypto('encrypt', $_POST['patientFname'], $vldata['patient_art_no']);
     //$vldata['patient_last_name'] = $general->crypto('encrypt', $_POST['surName'], $vldata['patient_art_no']);

     /* Updating the high and low viral load data */
     if ($vldata['result_status'] == 4 || $vldata['result_status'] == 7) {
          $vlDb = new \Vlsm\Models\Vl();
          $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
     }
     if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
          $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
          $id = $db->update($tableName, $vldata);
     } else {
          if ($_SESSION['instanceType'] == 'remoteuser') {
               $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
               $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] : NULL;
               $vldata['remote_sample'] = 'yes';
          } else {
               $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : NULL;
               $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] : NULL;
          }
          $vldata['sample_code_format'] = (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '') ? $_POST['sampleCodeFormat'] : NULL;
          $id = $db->insert($tableName, $vldata);
     }
     //echo $id;die;
     if ($id > 0) {
          $_SESSION['alertMsg'] = "VL request added successfully";
          //Add event log
          $eventType = 'add-vl-request-png';
          $action = ucwords($_SESSION['userName']) . ' added a new request data with the sample code ' . $_POST['sampleCode'];
          $resource = 'vl-request-png';

          $general->activityLog($eventType, $action, $resource);

          //   $data=array(
          //        'event_type'=>$eventType,
          //        'action'=>$action,
          //        'resource'=>$resource,
          //        'date_time'=>$general->getDateTime()
          //   );
          //   $db->insert($tableName1,$data);
          if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
               $_SESSION['treamentId'] = $id;
               header("location:addVlRequest.php");
          } else {
               $_SESSION['treamentId'] = '';
               $_SESSION['facilityId'] = '';
               unset($_SESSION['treamentId']);
               header("location:vlRequest.php");
          }
     } else {
          $_SESSION['alertMsg'] = "Please try again later";
     }
} catch (Exception $exc) {
     error_log($exc->getMessage());
     error_log($exc->getTraceAsString());
}
