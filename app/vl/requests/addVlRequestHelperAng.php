<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
     session_start();
}




/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_vl";
$tableName1 = "activity_log";
$vlTestReasonTable = "r_vl_test_reasons";
$fDetails = "facility_details";
$vl_result_category = null;
try {
     //system config
     $systemConfigQuery = "SELECT * from system_config";
     $systemConfigResult = $db->query($systemConfigQuery);
     $sarr = [];
     // now we create an associative array so that we can easily create view variables
     for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
          $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
     }
     $status = 6;
     if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
          $status = 9;
     }
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
     if (isset($_POST['requestingDate']) && trim($_POST['requestingDate']) != "") {
          $_POST['requestingDate'] = DateUtility::isoDateFormat($_POST['requestingDate']);
     } else {
          $_POST['requestingDate'] = null;
     }
     if (isset($_POST['newArtRegimen']) && trim($_POST['newArtRegimen']) != "") {
          $artQuery = "SELECT art_id,art_code FROM r_vl_art_regimen where (art_code='" . $_POST['newArtRegimen'] . "' OR art_code='" . strtolower($_POST['newArtRegimen']) . "' OR art_code='" . (strtolower($_POST['newArtRegimen'])) . "')";
          $artResult = $db->rawQuery($artQuery);
          if (!isset($artResult[0]['art_id'])) {
               $data = array(
                    'art_code' => $_POST['newArtRegimen'],
                    'parent_art' => '8',
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
               );
               $result = $db->insert('r_vl_art_regimen', $data);
               $_POST['artRegimen'] = $_POST['newArtRegimen'];
          } else {
               $_POST['artRegimen'] = $artResult[0]['art_code'];
          }
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
     $isRejection = false;
     if (isset($_POST['noResult']) && $_POST['noResult'] == 'yes') {
          $vl_result_category = 'rejected';
          $isRejection = true;
          $_POST['vlResult'] = '';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['tnd']) && $_POST['tnd'] == 'yes' && !$isRejection) {
          $_POST['vlResult'] = 'Target Not Detected';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['ldl']) && $_POST['ldl'] == 'yes' && !$isRejection) {
          $_POST['vlResult'] = 'Low Detection Level';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['hdl']) && $_POST['hdl'] == 'yes' && !$isRejection) {
          $_POST['vlResult'] = 'High Detection Level';
          $_POST['vlLog'] = '';
     }
     if (isset($_POST['vlResult']) && trim($_POST['vlResult']) != '') {
          $_POST['result'] = $_POST['vlResult'];
     } else if ($_POST['vlLog'] != '') {
          $_POST['result'] = $_POST['vlLog'];
     }
     //set patient group
     $patientGroup = [];
     if (isset($_POST['patientGroup'])) {
          if ($_POST['patientGroup'] == 'general_population') {
               $patientGroup['patient_group'] = 'general_population';
          } else if ($_POST['patientGroup'] == 'key_population') {
               $patientGroup['patient_group'] = 'key_population';
               //$patientGroup['patient_group_option'] = $_POST['patientGroupKeyOption'];
               if ($_POST['patientGroupKeyOption'] == 'other') {
                    $patientGroup['patient_group_option_other'] = $_POST['patientGroupKeyOtherText'];
               }
          } else if ($_POST['patientGroup'] == 'pregnant') {
               $patientGroup['patient_group'] = 'pregnant';
               $patientGroup['patient_group_option_date'] = $_POST['patientPregnantWomanDate'];
          } else if ($_POST['patientGroup'] == 'breast_feeding') {
               $patientGroup['patient_group'] = 'breast_feeding';
          }
     }
     if ($_SESSION['instanceType'] == 'remoteuser') {
          $sampleCode = 'remote_sample_code';
          $sampleCodeKey = 'remote_sample_code_key';
     } else {
          $sampleCode = 'sample_code';
          $sampleCodeKey = 'sample_code_key';
     }
     //check existing sample code
     $existSampleQuery = "SELECT " . $sampleCode . "," . $sampleCodeKey . " FROM form_vl where " . $sampleCode . " ='" . trim($_POST['sampleCode']) . "'";
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
               header("Location:addVlRequest.php");
          }
     }
     if (isset($_POST['reviewedOn']) && trim($_POST['reviewedOn']) != "") {
          $reviewedOn = explode(" ", $_POST['reviewedOn']);
          $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
     } else {
          $_POST['reviewedOn'] = null;
     }

     $vldata = array(
          'vlsm_instance_id' => $instanceId,
          'vlsm_country_id' => 8,
          'sample_code_format' => (isset($_POST['sampleCodeFormat']) && $_POST['sampleCodeFormat'] != '') ? $_POST['sampleCodeFormat'] :  null,
          //'sample_code_key'=>(isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey']!='') ? $_POST['sampleCodeKey'] :  null,
          'facility_id' => (isset($_POST['fName']) && $_POST['fName'] != '') ? $_POST['fName'] :  null,
          'sample_collection_date' => $_POST['sampleCollectionDate'],
          //'patient_first_name'=>(isset($_POST['patientFirstName']) && $_POST['patientFirstName']!='') ? $_POST['patientFirstName'] :  null,
          'patient_province' => (isset($_POST['patientDistrict']) && $_POST['patientDistrict'] != '') ? $_POST['patientDistrict'] :  null,
          'patient_district' => (isset($_POST['patientProvince']) && $_POST['patientProvince'] != '') ? $_POST['patientProvince'] :  null,
          'patient_responsible_person' => (isset($_POST['responsiblePersonName']) && $_POST['responsiblePersonName'] != '') ? $_POST['responsiblePersonName'] :  null,
          'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] :  null,
          'patient_dob' => $_POST['dob'],
          'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] :  null,
          'patient_art_no' => (isset($_POST['patientArtNo']) && $_POST['patientArtNo'] != '') ? $_POST['patientArtNo'] :  null,
          'treatment_initiated_date' => $_POST['dateOfArtInitiation'],
          'current_regimen' => (isset($_POST['artRegimen']) && $_POST['artRegimen'] != '') ? $_POST['artRegimen'] :  null,
          'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] :  null,
          'patient_group' => json_encode($patientGroup),
          'sample_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] :  null,
          'line_of_treatment' => (isset($_POST['lineTreatment']) && $_POST['lineTreatment'] != '') ? $_POST['lineTreatment'] :  null,
          'line_of_treatment_ref_type' => (isset($_POST['lineTreatmentRefType']) && $_POST['lineTreatmentRefType'] != '') ? $_POST['lineTreatmentRefType'] :  null,
          'request_clinician_name' => (isset($_POST['reqClinician']) && $_POST['reqClinician'] != '') ? $_POST['reqClinician'] :  null,
          'request_clinician_phone_number' => (isset($_POST['reqClinicianPhoneNumber']) && $_POST['reqClinicianPhoneNumber'] != '') ? $_POST['reqClinicianPhoneNumber'] :  null,
          //'test_requested_on'=>(isset($_POST['requestDate']) && $_POST['requestDate']!='') ? \App\Utilities\DateUtility::isoDateFormat($_POST['requestDate']) :  null,
          'vl_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] :  null,
          //'vl_focal_person_phone_number'=>(isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber']!='') ? $_POST['vlFocalPersonPhoneNumber'] :  null,
          'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] :  null,
          'lab_technician' => (isset($_POST['labTechnician']) && $_POST['labTechnician'] != '') ? $_POST['labTechnician'] :  null,
          'consent_to_receive_sms' => (isset($_POST['consentReceiveSms']) && $_POST['consentReceiveSms'] != '') ? $_POST['consentReceiveSms'] :  null,
          'requesting_vl_service_sector' => (isset($_POST['sector']) && $_POST['sector'] != '') ? $_POST['sector'] :  null,
          'requesting_category' => (isset($_POST['category']) && $_POST['category'] != '') ? $_POST['category'] :  null,
          'requesting_professional_number' => (isset($_POST['profNumber']) && $_POST['profNumber'] != '') ? $_POST['profNumber'] :  null,
          'requesting_facility_id' => (isset($_POST['clinicName']) && $_POST['clinicName'] != '') ? $_POST['clinicName'] :  null,
          'requesting_person' => (isset($_POST['requestingPerson']) && $_POST['requestingPerson'] != '') ? $_POST['requestingPerson'] :  null,
          'requesting_phone' => (isset($_POST['requestingContactNo']) && $_POST['requestingContactNo'] != '') ? $_POST['requestingContactNo'] :  null,
          'requesting_date' => (isset($_POST['requestingDate']) && $_POST['requestingDate'] != '') ? $_POST['requestingDate'] :  null,
          'collection_site' => (isset($_POST['collectionSite']) && $_POST['collectionSite'] != '') ? $_POST['collectionSite'] :  null,
          'vl_test_platform' => $testingPlatform,
          'test_methods' => (isset($_POST['testMethods']) && $_POST['testMethods'] != '') ? $_POST['testMethods'] :  null,
          'sample_received_at_vl_lab_datetime' => $_POST['sampleReceivedDate'],
          'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
          'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
          'is_sample_rejected' => (isset($_POST['noResult']) && $_POST['noResult'] != '') ? $_POST['noResult'] :  null,
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] :  null,
          'result_value_absolute' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? $_POST['vlResult'] :  null,
          'result_value_absolute_decimal' => (isset($_POST['vlResult']) && $_POST['vlResult'] != '' && ($_POST['vlResult'] != 'Target Not Detected' && $_POST['vlResult'] != 'Low Detection Level' && $_POST['vlResult'] != 'High Detection Level')) ? number_format((float)$_POST['vlResult'], 2, '.', '') :  null,
          'result' => (isset($_POST['result']) && $_POST['result'] != '') ? $_POST['result'] :  null,
          'result_value_log' => (isset($_POST['vlLog']) && $_POST['vlLog'] != '') ? $_POST['vlLog'] :  null,
          'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : "",
          'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
          'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] :  null,
          'lab_tech_comments' => (isset($_POST['labComments']) && trim($_POST['labComments']) != '') ? trim($_POST['labComments']) :  null,
          'result_status' => $status,
          'request_created_by' => $_SESSION['userId'],
          'request_created_datetime' => $db->now(),
          'last_modified_by' => $_SESSION['userId'],
          'last_modified_datetime' => $db->now(),
          'manual_result_entry' => 'yes',
          'vl_result_category' => $vl_result_category
     );


     $vlService = ContainerRegistry::get(VlService::class);
     $vldata['vl_result_category'] = $vlService->getVLResultCategory($vldata['result_status'], $vldata['result']);

     if ($vldata['vl_result_category'] == 'failed' || $vldata['vl_result_category'] == 'invalid') {
          $vldata['result_status'] = 5;
     } elseif ($vldata['vl_result_category'] == 'rejected') {
          $vldata['result_status'] = 4;
     }
     $vldata['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFirstName'], $vldata['patient_art_no']);
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
          $lastVlDate = (isset($_POST['lastVlDate']) && $_POST['lastVlDate'] != '') ? DateUtility::isoDateFormat($_POST['lastVlDate']) :  null;
          $lastVlResult = (isset($_POST['lastVlResult']) && $_POST['lastVlResult'] != '') ? $_POST['lastVlResult'] :  null;
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
     if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '') {
          $db = $db->where('vl_sample_id', $_POST['vlSampleId']);
          $id = $db->update($tableName, $vldata);
     } else {
          if ($_SESSION['instanceType'] == 'remoteuser') {
               $vldata['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
               $vldata['remote_sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
               $vldata['remote_sample'] = 'yes';
          } else {
               $vldata['sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] :  null;
               $vldata['sample_code_key'] = (isset($_POST['sampleCodeKey']) && $_POST['sampleCodeKey'] != '') ? $_POST['sampleCodeKey'] :  null;
          }
          $id = $db->insert($tableName, $vldata);
     }

     if ($id > 0) {
          $_SESSION['alertMsg'] = "VL request added successfully";
          //Add event log
          $eventType = 'add-vl-request-ang';
          $action = $_SESSION['userName'] . ' added a new request data with the sample code ' . $_POST['sampleCode'];
          $resource = 'vl-request-ang';

          $general->activityLog($eventType, $action, $resource);

          //   $data=array(
          //        'event_type'=>$eventType,
          //        'action'=>$action,
          //        'resource'=>$resource,
          //        'date_time'=>\App\Utilities\DateUtility::getCurrentDateTime()
          //   );
          //   $db->insert($tableName1,$data);

          if (isset($_POST['saveNext']) && $_POST['saveNext'] == 'next') {
               header("Location:addVlRequest.php");
          } else {
               header("Location:vlRequest.php");
          }
     } else {
          $_SESSION['alertMsg'] = "Please try again later";
     }
} catch (Exception $exc) {
     error_log($exc->getMessage());
     error_log($exc->getTraceAsString());
}
