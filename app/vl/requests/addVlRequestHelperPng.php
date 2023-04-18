<?php

use App\Models\General;
use App\Models\Vl;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
ob_start();



$general = new General();
$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$vl_result_category = null;
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
          $_POST['dob'] = DateUtils::isoDateFormat($_POST['dob']);
     } else {
          $_POST['dob'] = null;
     }

     if (isset($_POST['collectionDate']) && trim($_POST['collectionDate']) != "") {
          $sampleDate = explode(" ", $_POST['collectionDate']);
          $_POST['collectionDate'] = DateUtils::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];
     } else {
          $_POST['collectionDate'] = null;
     }
     if (isset($_POST['failedTestDate']) && trim($_POST['failedTestDate']) != "") {
          $failedtestDate = explode(" ", $_POST['failedTestDate']);
          $_POST['failedTestDate'] = DateUtils::isoDateFormat($failedtestDate[0]) . " " . $failedtestDate[1];
     } else {
          $_POST['failedTestDate'] = null;
     }

     if (isset($_POST['regStartDate']) && trim($_POST['regStartDate']) != "") {
          $_POST['regStartDate'] = DateUtils::isoDateFormat($_POST['regStartDate']);
     } else {
          $_POST['regStartDate'] = null;
     }

     if (isset($_POST['receivedDate']) && trim($_POST['receivedDate']) != "") {
          $sampleReceivedDate = explode(" ", $_POST['receivedDate']);
          $_POST['receivedDate'] = DateUtils::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
     } else {
          $_POST['receivedDate'] = null;
     }
     if (isset($_POST['testDate']) && trim($_POST['testDate']) != "") {
          $sampletestDate = explode(" ", $_POST['testDate']);
          $_POST['testDate'] = DateUtils::isoDateFormat($sampletestDate[0]) . " " . $sampletestDate[1];
     } else {
          $_POST['testDate'] = null;
     }
     if (isset($_POST['cdDate']) && trim($_POST['cdDate']) != "") {
          $_POST['cdDate'] = DateUtils::isoDateFormat($_POST['cdDate']);
     } else {
          $_POST['cdDate'] = null;
     }
     if (isset($_POST['qcDate']) && trim($_POST['qcDate']) != "") {
          $_POST['qcDate'] = DateUtils::isoDateFormat($_POST['qcDate']);
     } else {
          $_POST['qcDate'] = null;
     }
     if (isset($_POST['clinicDate']) && trim($_POST['clinicDate']) != "") {
          $_POST['clinicDate'] = DateUtils::isoDateFormat($_POST['clinicDate']);
     } else {
          $_POST['clinicDate'] = null;
     }
     if (isset($_POST['reportDate']) && trim($_POST['reportDate']) != "") {
          $_POST['reportDate'] = DateUtils::isoDateFormat($_POST['reportDate']);
     } else {
          $_POST['reportDate'] = null;
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
          $_POST['rejectionReason'] = null;
     }
     if (isset($_POST['sampleQuality']) && trim($_POST['sampleQuality']) == 'yes') {
          $vl_result_category = 'rejected';
          $_POST['vlResult'] = null;
          $status = 4;
     }

     $reasonForTestField = null;
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
     $instanceId = '';
     if (isset($_SESSION['instanceId'])) {
          $instanceId = $_SESSION['instanceId'];
     }
     $vldata = array(
          //'sample_code'=>(isset($_POST['sampleCode']) && $_POST['sampleCode']!='') ? $_POST['sampleCode'] :  null,
          //'sample_code_format'=>(isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat']!='')? $_POST['sampleCodeFormat'] :  null,
          //'sample_code_key'=>(isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey']!='')? $_POST['sampleCodeKey'] :  null,
          'vlsm_instance_id' => $instanceId,
          'vlsm_country_id' => '5',
          'facility_id' => (isset($_POST['fName']) && trim($_POST['fName']) != '') ? $_POST['fName'] : null,
          'province_id' => (isset($_POST['provinceId']) && !empty($_POST['provinceId'])) ? $_POST['provinceId'] :  null,
          //'ward'=>(isset($_POST['wardData']) && $_POST['wardData']!='' ? $_POST['wardData'] : null),
          'patient_art_no' => (isset($_POST['patientARTNo']) && trim($_POST['patientARTNo']) != '') ? $_POST['patientARTNo'] : null,
          'request_clinician_name' => (isset($_POST['officerName']) && $_POST['officerName'] != '' ? $_POST['officerName'] : null),
          'lab_phone_number' => (isset($_POST['telephone']) && $_POST['telephone'] != '' ? $_POST['telephone'] : null),
          //'patient_first_name'=>(isset($_POST['patientFname']) && $_POST['patientFname']!='' ? $_POST['patientFname'] : null),
          //'patient_last_name'=>(isset($_POST['surName']) && $_POST['surName']!='' ? $_POST['surName'] : null),
          'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '' ? $_POST['gender'] : null),
          'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] : null,
          'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] : null,
          'patient_dob' => $_POST['dob'],
          'patient_age_in_years' => (isset($_POST['ageInYears']) && $_POST['ageInYears'] != '') ? $_POST['ageInYears'] : null,
          'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] : null,
          'line_of_treatment' => (isset($_POST['artLine']) && $_POST['artLine'] != '') ? $_POST['artLine'] : null,
          'current_regimen' => (isset($_POST['currentRegimen']) && $_POST['currentRegimen'] != '') ? $_POST['currentRegimen'] : null,
          'date_of_initiation_of_current_regimen' => $_POST['regStartDate'],
          'art_cd_cells' => (isset($_POST['cdCells']) && $_POST['cdCells'] != '' ? $_POST['cdCells'] : null),
          'art_cd_date' => $_POST['cdDate'],
          'who_clinical_stage' => (isset($_POST['clinicalStage']) && $_POST['clinicalStage'] != '' ? $_POST['clinicalStage'] : null),
          //'reason_testing_png'=>(isset($_POST['reasonForTest']) && $_POST['reasonForTest']!='' ? $_POST['reasonForTest'] : null),
          'reason_for_vl_testing' => $reasonForTestField,
          'reason_for_vl_testing_other' => (isset($_POST['reason']) && $_POST['reason'] != '' ? $_POST['reason'] : null),
          'sample_to_transport' => (isset($_POST['typeOfSample']) && $_POST['typeOfSample'] != '' ? $_POST['typeOfSample'] : null),
          'whole_blood_ml' => (isset($_POST['wholeBloodOne']) && $_POST['wholeBloodOne'] != '' ? $_POST['wholeBloodOne'] : null),
          'whole_blood_vial' => (isset($_POST['wholeBloodTwo']) && $_POST['wholeBloodTwo'] != '' ? $_POST['wholeBloodTwo'] : null),
          'plasma_ml' => (isset($_POST['plasmaOne']) && $_POST['plasmaOne'] != '' ? $_POST['plasmaOne'] : null),
          'plasma_vial' => (isset($_POST['plasmaTwo']) && $_POST['plasmaTwo'] != '' ? $_POST['plasmaTwo'] : null),
          'plasma_process_time' => (isset($_POST['processTime']) && $_POST['processTime'] != '' ? $_POST['processTime'] : null),
          'plasma_process_tech' => (isset($_POST['processTech']) && $_POST['processTech'] != '' ? $_POST['processTech'] : null),
          'is_sample_rejected' => (isset($_POST['sampleQuality']) && $_POST['sampleQuality'] != '') ? $_POST['sampleQuality'] : null,
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : null,
          'batch_quality' => (isset($_POST['batchQuality']) && $_POST['batchQuality'] != '' ? $_POST['batchQuality'] : null),
          'sample_test_quality' => (isset($_POST['testQuality']) && $_POST['testQuality'] != '' ? $_POST['testQuality'] : null),
          'sample_batch_id' => (isset($_POST['batchNo']) && $_POST['batchNo'] != '' ? $_POST['batchNo'] : null),
          'failed_test_date' => $_POST['failedTestDate'],
          'failed_test_tech' => (isset($_POST['failedTestingTech']) && $_POST['failedTestingTech'] != '') ? $_POST['failedTestingTech'] : null,
          'failed_vl_result' => (isset($_POST['failedvlResult']) && $_POST['failedvlResult'] != '' ? $_POST['failedvlResult'] : null),
          'failed_batch_quality' => (isset($_POST['failedbatchQuality']) && $_POST['failedbatchQuality'] != '' ? $_POST['failedbatchQuality'] : null),
          'failed_sample_test_quality' => (isset($_POST['failedtestQuality']) && $_POST['failedtestQuality'] != '' ? $_POST['failedtestQuality'] : null),
          'failed_batch_id' => (isset($_POST['failedbatchNo']) && $_POST['failedbatchNo'] != '' ? $_POST['failedbatchNo'] : null),
          'sample_collection_date' => $_POST['collectionDate'],
          'sample_collected_by' => (isset($_POST['collectedBy']) && $_POST['collectedBy'] != '' ? $_POST['collectedBy'] : null),
          'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '' ? $_POST['labId'] : null),
          'sample_type' => (isset($_POST['sampleType']) && $_POST['sampleType'] != '' ? $_POST['sampleType'] : null),
          'sample_received_at_vl_lab_datetime' => $_POST['receivedDate'],
          'tech_name_png' => (isset($_POST['techName']) && $_POST['techName'] != '') ? $_POST['techName'] : null,
          'sample_tested_datetime' => (isset($_POST['testDate']) && $_POST['testDate'] != '' ? $_POST['testDate'] : null),
          //'last_viral_load_result'=>(isset($_POST['vlResult']) && $_POST['vlResult']!='' ? $_POST['vlResult'] : null),
          'vl_test_platform' => (isset($_POST['testingTech']) && $_POST['testingTech'] != '') ? $_POST['testingTech'] : null,
          'cphl_vl_result' => (isset($_POST['cphlvlResult']) && $_POST['cphlvlResult'] != '' ? $_POST['cphlvlResult'] : null),
          'result' => (isset($_POST['finalViralResult']) && trim($_POST['finalViralResult']) != '') ? $_POST['finalViralResult'] : null,
          'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
          'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
          'result_approved_by'         => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
          'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] :  null,
          'qc_tech_name' => (isset($_POST['qcTechName']) && $_POST['qcTechName'] != '' ? $_POST['qcTechName'] : null),
          'qc_tech_sign' => (isset($_POST['qcTechSign']) && $_POST['qcTechSign'] != '' ? $_POST['qcTechSign'] : null),
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
     } else if (!empty($_POST['api']) && $_POST['api'] == "yes") {
          $vldata['source_of_request'] = 'api';
     }

     //$vldata['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFname'], $vldata['patient_art_no']);
     //$vldata['patient_last_name'] = $general->crypto('doNothing', $_POST['surName'], $vldata['patient_art_no']);


     $vlDb = new Vl();
     $vldata['vl_result_category'] = $vlDb->getVLResultCategory($vldata['result_status'], $vldata['result']);
     if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
          $vldata['result_status'] = 5;
     } elseif ($vldata['vl_result_category'] == 'rejected') {
          $vldata['result_status'] = 4;
     }

     if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
          $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
          $id = $db->update($tableName, $vldata);
     } else {
          if ($_SESSION['instanceType'] == 'remoteuser') {
               $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : null;
               $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] : null;
               $vldata['remote_sample'] = 'yes';
          } else {
               $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : null;
               $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] : null;
          }
          $vldata['sample_code_format'] = (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '') ? $_POST['sampleCodeFormat'] : null;
          $id = $db->insert($tableName, $vldata);
     }
     //echo $id;die;
     if ($id > 0) {
          $_SESSION['alertMsg'] = "VL request added successfully";
          //Add event log
          $eventType = 'add-vl-request-png';
          $action = $_SESSION['userName'] . ' added a new request data with the sample code ' . $_POST['sampleCode'];
          $resource = 'vl-request-png';

          $general->activityLog($eventType, $action, $resource);

          //   $data=array(
          //        'event_type'=>$eventType,
          //        'action'=>$action,
          //        'resource'=>$resource,
          //        'date_time'=>\App\Utilities\DateUtils::getCurrentDateTime()
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
