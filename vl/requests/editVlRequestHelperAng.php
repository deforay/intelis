<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/includes/MysqliDb.php');
include_once(APPLICATION_PATH . '/models/General.php');
$general = new General($db);
$tableName = "vl_request_form";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$fDetails = "facility_details";
try {
     //system config
     $systemConfigQuery = "SELECT * from system_config";
     $systemConfigResult = $db->query($systemConfigQuery);
     $sarr = array();
     // now we create an associative array so that we can easily create view variables
     for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
          $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
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
     if (isset($_POST['requestingDate']) && trim($_POST['requestingDate']) != "") {
          $_POST['requestingDate'] = $general->dateFormat($_POST['requestingDate']);
     } else {
          $_POST['requestingDate'] = NULL;
     }

     if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
          $artQuery = "SELECT art_id,art_code FROM r_art_code_details where (art_code='" . $_POST['newArtRegimen'] . "' OR art_code='" . strtolower($_POST['newArtRegimen']) . "' OR art_code='" . ucfirst(strtolower($_POST['newArtRegimen'])) . "') AND nation_identifier='rwd'";
          $artResult = $db->rawQuery($artQuery);
          if (!isset($artResult[0]['art_id'])) {
               $data = array(
                    'art_code' => $_POST['newArtRegimen'],
                    'nation_identifier' => 'ang',
                    'parent_art' => '8',
                    'updated_datetime' => $general->getDateTime(),
               );
               $result = $db->insert('r_art_code_details', $data);
               $_POST['artRegimen'] = $_POST['newArtRegimen'];
          } else {
               $_POST['artRegimen'] = $artResult[0]['art_code'];
          }
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
          $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower($_POST['newRejectionReason']) . "' OR rejection_reason_name='" . ucfirst(strtolower($_POST['newRejectionReason'])) . "'";
          $rejectionResult = $db->rawQuery($rejectionReasonQuery);
          if (!isset($rejectionResult[0]['rejection_reason_id'])) {
               $data = array(
                    'rejection_reason_name' => $_POST['newRejectionReason'],
                    'rejection_type' => 'general',
                    'rejection_reason_status' => 'active',
                    'updated_datetime' => $general->getDateTime(),
               );
               $id = $db->insert('r_sample_rejection_reasons', $data);
               $_POST['rejectionReason'] = $id;
          } else {
               $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
          }
     }

     $isRejection = false;
     if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
          $isRejection = true;
          $_POST['vlResult'] = '';
          $_POST['vlLog'] = '';
     }

     if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && $isRejection == false) {
          $_POST['vlResult'] = 'Target Not Detected';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['ldl']) && $_POST['ldl'] == 'yes' && $isRejection == false) {
          $_POST['vlResult'] = 'Low Detection Level';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['hdl']) && $_POST['hdl'] == 'yes' && $isRejection == false) {
          $_POST['vlResult'] = 'High Detection Level';
          $_POST['vlLog'] = '';
     }

     if (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') {
          $_POST['result'] = $_POST['vlResult'];
     } else if ($_POST['vlLog'] != '') {
          $_POST['result'] = $_POST['vlLog'];
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
     //set patient group
     $patientGroup = array();
     if (isset($_POST['patientGroup'])) {
          if ($_POST['patientGroup'] == 'general_population') {
               $patientGroup['patient_group'] = 'general_population';
          } else if ($_POST['patientGroup'] == 'key_population') {
               $patientGroup['patient_group'] = 'general_population';
               //$patientGroup['patient_group_option'] = $_POST['patientGroupKeyOption'];
               if (isset($_POST['patientGroupKeyOption']) && $_POST['patientGroupKeyOption'] == 'other') {
                    $patientGroup['patient_group_option_other'] = $_POST['patientGroupKeyOtherText'];
               }
          } else if ($_POST['patientGroup'] == 'pregnant') {
               $patientGroup['patient_group'] = 'pregnant';
               $patientGroup['patient_group_option'] = $_POST['patientPregnantWomanDate'];
          } else if ($_POST['patientGroup'] == 'breast_feeding') {
               $patientGroup['patient_group'] = 'breast_feeding';
          }
     }
     $vldata = array(
          'facility_id' => (isset($_POST['fName']) && $_POST['fName'] != '') ? $_POST['fName'] :  NULL,
          'sample_collection_date' => $_POST['sampleCollectionDate'],
          //'patient_first_name'=>(isset($_POST['patientFirstName']) && $_POST['patientFirstName']!='') ? $_POST['patientFirstName'] :  NULL,
          'patient_province' => (isset($_POST['patientDistrict']) && $_POST['patientDistrict'] != '') ? $_POST['patientDistrict'] :  NULL,
          'patient_district' => (isset($_POST['patientProvince']) && $_POST['patientProvince'] != '') ? $_POST['patientProvince'] :  NULL,
          'patient_responsible_person' => (isset($_POST['responsiblePersonName']) && $_POST['responsiblePersonName'] != '') ? $_POST['responsiblePersonName'] :  NULL,
          'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] :  NULL,
          'patient_dob' => $_POST['dob'],
          'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] :  NULL,
          'patient_art_no' => (isset($_POST['patientArtNo']) && $_POST['patientArtNo'] != '') ? $_POST['patientArtNo'] :  NULL,
          'treatment_initiated_date' => $_POST['dateOfArtInitiation'],
          'current_regimen' => (isset($_POST['artRegimen']) && $_POST['artRegimen'] != '') ? $_POST['artRegimen'] :  NULL,
          'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] :  NULL,
          'patient_group' => json_encode($patientGroup),
          'sample_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] :  NULL,
          'line_of_treatment' => (isset($_POST['lineTreatment']) && $_POST['lineTreatment'] != '') ? $_POST['lineTreatment'] :  NULL,
          'line_of_treatment_ref_type' => (isset($_POST['lineTreatmentRefType']) && $_POST['lineTreatmentRefType'] != '') ? $_POST['lineTreatmentRefType'] :  NULL,
          'request_clinician_name' => (isset($_POST['reqClinician']) && $_POST['reqClinician'] != '') ? $_POST['reqClinician'] :  NULL,
          'request_clinician_phone_number' => (isset($_POST['reqClinicianPhoneNumber']) && $_POST['reqClinicianPhoneNumber'] != '') ? $_POST['reqClinicianPhoneNumber'] :  NULL,
          //'test_requested_on'=>(isset($_POST['requestDate']) && $_POST['requestDate']!='') ? $general->dateFormat($_POST['requestDate']) :  NULL,
          'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] :  NULL,
          //'vl_focal_person_phone_number'=>(isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber']!='') ? $_POST['vlFocalPersonPhoneNumber'] :  NULL,
          'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  NULL,
          'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  NULL,
          'consent_to_receive_sms' => (isset($_POST['consentReceiveSms']) && $_POST['consentReceiveSms'] != '') ? $_POST['consentReceiveSms'] :  NULL,
          'requesting_vl_service_sector' => (isset($_POST['sector']) && $_POST['sector'] != '') ? $_POST['sector'] :  NULL,
          'requesting_category' => (isset($_POST['category']) && $_POST['category'] != '') ? $_POST['category'] :  NULL,
          'requesting_professional_number' => (isset($_POST['profNumber']) && $_POST['profNumber'] != '') ? $_POST['profNumber'] :  NULL,
          'requesting_facility_id' => (isset($_POST['clinicName']) && $_POST['clinicName'] != '') ? $_POST['clinicName'] :  NULL,
          'requesting_person' => (isset($_POST['requestingPerson']) && $_POST['requestingPerson'] != '') ? $_POST['requestingPerson'] :  NULL,
          'requesting_phone' => (isset($_POST['requestingContactNo']) && $_POST['requestingContactNo'] != '') ? $_POST['requestingContactNo'] :  NULL,
          'requesting_date' => $_POST['requestingDate'],
          'collection_site' => (isset($_POST['collectionSite']) && $_POST['collectionSite'] != '') ? $_POST['collectionSite'] :  NULL,
          'vl_test_platform' => $testingPlatform,
          'test_methods' => (isset($_POST['testMethods']) && $_POST['testMethods'] != '') ? $_POST['testMethods'] :  NULL,
          'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
          'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
          'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
          'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  NULL,
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  NULL,
          'result_value_absolute' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? $_POST['vlResult'] :  NULL,
          'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  NULL,
          'result' => (isset($_POST['result']) && $_POST['result'] != '') ? $_POST['result'] :  NULL,
          'result_value_log' => (isset($_POST['vlLog']) && $_POST['vlLog'] != '') ? $_POST['vlLog'] :  NULL,
          'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  NULL,
          'approver_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) :  NULL,
          'reason_for_vl_result_changes' => $allChange,
          'last_modified_by' => $_SESSION['userId'],
          'last_modified_datetime' => $general->getDateTime(),
          'data_sync' => 0
     );
     if ($sarr['user_type'] == 'remoteuser') {
          $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  NULL;
     } else if ($_POST['sampleCodeCol'] != '') {
          $vldata['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] :  NULL;
          $vldata['result_status'] = (isset($_POST['status']) && $_POST['status'] != '') ? $_POST['status'] :  NULL;
     }

     $vldata['patient_first_name'] = $general->crypto('encrypt', $_POST['patientFirstName'], $vldata['patient_art_no']);


     if (isset($_POST['indicateVlTesing']) && $_POST['indicateVlTesing'] != '') {

          $reasonQuery = "SELECT test_reason_id FROM r_vl_test_reasons where test_reason_name='" . $_POST['indicateVlTesing'] . "'";
          $reasonResult = $db->rawQuery($reasonQuery);
          if (isset($reasonResult[0]['test_reason_id']) && $reasonResult[0]['test_reason_id'] != '') {
               $_POST['indicateVlTesing'] = $reasonResult[0]['test_reason_id'];
          } else {
               $data = array(
                    'test_reason_name' => $_POST['indicateVlTesing'],
                    'test_reason_status' => 'active'
               );
               $id = $db->insert('r_vl_test_reasons', $data);
               $_POST['indicateVlTesing'] = $id;
          }

          $vldata['reason_for_vl_testing'] = $_POST['indicateVlTesing'];
          $lastVlDate = (isset($_POST['lastVlDate']) && $_POST['lastVlDate'] != '') ? $general->dateFormat($_POST['lastVlDate']) :  NULL;
          $lastVlResult = (isset($_POST['lastVlResult']) && $_POST['lastVlResult'] != '') ? $_POST['lastVlResult'] :  NULL;
          if ($_POST['indicateVlTesing'] == 'routine') {
               $vldata['last_vl_date_routine'] = $lastVlDate;
               $vldata['last_vl_result_routine'] = $lastVlResult;
          } else if ($_POST['indicateVlTesing'] == 'expose') {
               $vldata['last_vl_date_ecd'] = $lastVlDate;
               $vldata['last_vl_result_ecd'] = $lastVlResult;
          } else if ($_POST['indicateVlTesing'] == 'suspect') {
               $vldata['last_vl_date_failure'] = $lastVlDate;
               $vldata['last_vl_result_failure'] = $lastVlResult;
          } else if ($_POST['indicateVlTesing'] == 'repetition') {
               $vldata['last_vl_date_failure_ac'] = $lastVlDate;
               $vldata['last_vl_result_failure_ac'] = $lastVlResult;
          } else if ($_POST['indicateVlTesing'] == 'clinical') {
               $vldata['last_vl_date_cf'] = $lastVlDate;
               $vldata['last_vl_result_cf'] = $lastVlResult;
          } else if ($_POST['indicateVlTesing'] == 'immunological') {
               $vldata['last_vl_date_if'] = $lastVlDate;
               $vldata['last_vl_result_if'] = $lastVlResult;
          }
     }
     $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
     $id = $db->update($tableName, $vldata);
     if ($id > 0) {
          $_SESSION['alertMsg'] = "VL request updated successfully";
          //Add event log
          $eventType = 'update-vl-request-ang';
          $action = ucwords($_SESSION['userName']) . ' updated a request data with the sample code ' . $_POST['sampleCode'];
          $resource = 'vl-request-ang';

          $general->activityLog($eventType, $action, $resource);

          //   $data=array(
          //        'event_type'=>$eventType,
          //        'action'=>$action,
          //        'resource'=>$resource,
          //        'date_time'=>$general->getDateTime()
          //   );
          //   $db->insert($tableName1,$data);
          header("location:vlRequest.php");
     } else {
          $_SESSION['alertMsg'] = "Please try again later";
     }
} catch (Exception $exc) {
     error_log($exc->getMessage());
     error_log($exc->getTraceAsString());
}
