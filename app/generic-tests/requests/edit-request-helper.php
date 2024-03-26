<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\PatientsService;
use App\Utilities\ValidationUtility;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var PatientsService $patientsService */
$patientsService = ContainerRegistry::get(PatientsService::class);


$formId = (int) $general->getGlobalConfig('vl_form');

// echo "<pre>";print_r($_POST);die;
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$tableName = "form_generic";
$testTableName = "generic_test_results";
$vlTestReasonTable = "r_generic_test_reasons";
$fDetails = "facility_details";
$vl_result_category = null;
$vlResult = null;
$logVal = null;
$absDecimalVal = null;
$absVal = null;
$txtVal = null;
$resultStatus = null;
try {
     $mandatoryFields = [
          $_POST['sampleCode'],
          $_POST['sampleCollectionDate']
     ];
     if (ValidationUtility::validateMandatoryFields($mandatoryFields) === false) {
          $_SESSION['alertMsg'] = _translate("Please enter all mandatory fields to save the test request");
          header("Location:addVlRequest.php");
          die;
     }

     $sarr = $general->getSystemConfig();

     //add province
     $splitProvince = explode("##", (string) $_POST['province']);
     if (isset($splitProvince[0]) && trim($splitProvince[0]) != '') {
          $provinceQuery = "SELECT * from geographical_divisions where geo_name='" . $splitProvince[0] . "'";
          $provinceInfo = $db->query($provinceQuery);
          if (empty($provinceInfo)) {
               $db->insert('geographical_divisions', array('geo_name' => $splitProvince[0], 'geo_code' => $splitProvince[1]));
          }
     }
     if (isset($_POST['sampleCollectionDate']) && trim((string) $_POST['sampleCollectionDate']) != "") {
          $sampleDate = explode(" ", (string) $_POST['sampleCollectionDate']);
          $_POST['sampleCollectionDate'] = DateUtility::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];
     } else {
          $_POST['sampleCollectionDate'] = null;
     }

     if (isset($_POST['dob']) && trim((string) $_POST['dob']) != "") {
          $_POST['dob'] = DateUtility::isoDateFormat($_POST['dob']);
     } else {
          $_POST['dob'] = null;
     }

     //Sample type section
     if (isset($_POST['specimenType']) && trim((string) $_POST['specimenType']) != "") {
          if (trim((string) $_POST['specimenType']) != 2) {
               $_POST['conservationTemperature'] = null;
               $_POST['durationOfConservation'] = null;
          }
     } else {
          $_POST['specimenType'] = null;
          $_POST['conservationTemperature'] = null;
          $_POST['durationOfConservation'] = null;
     }

     //update facility code
     if (trim((string) $_POST['facilityCode']) != '') {
          $fData = array('facility_code' => $_POST['facilityCode']);
          $db->where('facility_id', $_POST['facilityId']);
          $id = $db->update($fDetails, $fData);
     }

     if (isset($_POST['gender']) && (trim((string) $_POST['gender']) == 'male' || trim((string) $_POST['gender']) == 'unreported')) {
          $_POST['patientPregnant'] = "N/A";
          $_POST['breastfeeding'] = "N/A";
     }

     $instanceId = '';
     if (isset($_SESSION['instanceId'])) {
          $instanceId = $_SESSION['instanceId'];
     }
     $testingPlatform = '';
     if (isset($_POST['testPlatform']) && trim((string) $_POST['testPlatform']) != '') {
          $platForm = explode("##", (string) $_POST['testPlatform']);
          $testingPlatform = $platForm[0];
     }
     if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) != "") {
          $sampleReceivedDateLab = explode(" ", (string) $_POST['sampleReceivedDate']);
          $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDateLab[0]) . " " . $sampleReceivedDateLab[1];
     } else {
          $_POST['sampleReceivedDate'] = null;
     }


     if (isset($_POST['sampleReceivedAtHubOn']) && trim((string) $_POST['sampleReceivedAtHubOn']) != "") {
          $sampleReceivedAtHubOn = explode(" ", (string) $_POST['sampleReceivedAtHubOn']);
          $_POST['sampleReceivedAtHubOn'] = DateUtility::isoDateFormat($sampleReceivedAtHubOn[0]) . " " . $sampleReceivedAtHubOn[1];
     } else {
          $_POST['sampleReceivedAtHubOn'] = null;
     }

     if (isset($_POST['approvedOn']) && trim((string) $_POST['approvedOn']) != "") {
          $approvedOn = explode(" ", (string) $_POST['approvedOn']);
          $_POST['approvedOn'] = DateUtility::isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
     } else {
          $_POST['approvedOn'] = null;
     }

     if (isset($_POST['sampleTestingDateAtLab']) && trim((string) $_POST['sampleTestingDateAtLab']) != "") {
          $sampleTestingDateAtLab = explode(" ", (string) $_POST['sampleTestingDateAtLab']);
          $_POST['sampleTestingDateAtLab'] = DateUtility::isoDateFormat($sampleTestingDateAtLab[0]) . " " . $sampleTestingDateAtLab[1];
     } else {
          $_POST['sampleTestingDateAtLab'] = null;
     }
     if (isset($_POST['resultDispatchedOn']) && trim((string) $_POST['resultDispatchedOn']) != "") {
          $resultDispatchedOn = explode(" ", (string) $_POST['resultDispatchedOn']);
          $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
     } else {
          $_POST['resultDispatchedOn'] = null;
     }
     if (isset($_POST['sampleDispatchedDate']) && trim((string) $_POST['sampleDispatchedDate']) != "") {
          $sampleDispatchedDate = explode(" ", (string) $_POST['sampleDispatchedDate']);
          $_POST['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
     } else {
          $_POST['sampleDispatchedDate'] = null;
     }

     if (isset($_POST['newRejectionReason']) && trim((string) $_POST['newRejectionReason']) != "") {
          $rejectionReasonQuery = "SELECT rejection_reason_id FROM r_generic_sample_rejection_reasons where rejection_reason_name='" . $_POST['newRejectionReason'] . "' OR rejection_reason_name='" . strtolower((string) $_POST['newRejectionReason']) . "' OR rejection_reason_name='" . (strtolower((string) $_POST['newRejectionReason'])) . "'";
          $rejectionResult = $db->rawQuery($rejectionReasonQuery);
          if (!isset($rejectionResult[0]['rejection_reason_id'])) {
               $data = [
                    'rejection_reason_name' => $_POST['newRejectionReason'],
                    'rejection_type' => 'general',
                    'rejection_reason_status' => 'active',
                    'updated_datetime' => DateUtility::getCurrentDateTime(),
               ];
               $id = $db->insert('r_generic_sample_rejection_reasons', $data);
               $_POST['rejectionReason'] = $id;
          } else {
               $_POST['rejectionReason'] = $rejectionResult[0]['rejection_reason_id'];
          }
     }

     $isRejected = false;
     if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
          $vl_result_category = 'rejected';
          $isRejected = true;
          $genericData['result_status'] = SAMPLE_STATUS\REJECTED;
     }

     $reasonForChanges = '';
     $allChange = [];
     if (isset($_POST['reasonForResultChangesHistory']) && $_POST['reasonForResultChangesHistory'] != '') {
          $allChange = json_decode(base64_decode((string) $_POST['reasonForResultChangesHistory']), true);
     }
     if (isset($_POST['reasonForResultChanges']) && trim((string) $_POST['reasonForResultChanges']) != '') {
          $allChange[] = array(
               'usr' => $_SESSION['userId'],
               'msg' => $_POST['reasonForResultChanges'],
               'dtime' => DateUtility::getCurrentDateTime()
          );
     }
     if (!empty($allChange)) {
          $reasonForChanges = json_encode($allChange);
     }

     if (isset($_POST['reviewedOn']) && trim((string) $_POST['reviewedOn']) != "") {
          $reviewedOn = explode(" ", (string) $_POST['reviewedOn']);
          $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
     } else {
          $_POST['reviewedOn'] = null;
     }

     if (isset($_POST['treatmentIndication']) && $_POST['treatmentIndication'] == "Other") {
          $_POST['treatmentIndication'] = $_POST['newTreatmentIndication'] . '_Other';
     }
     // $interpretationResult = null;
     /* if(isset($_POST['resultType']) && isset($_POST['testType']) && !empty($_POST['resultType']) && !empty($_POST['testType'])){
          $interpretationResult = $genericTestsService->getInterpretationResults($_POST['testType'], $_POST['result']);
     } */
     // if (!empty($_POST['resultInterpretation'])) {
     //      $interpretationResult = $_POST['resultInterpretation'];
     // }

     if (isset($_POST['subTestResult']) && is_array($_POST['subTestResult'])) {
          $_POST['subTestResult'] = implode("##", $_POST['subTestResult']);
     } else {
          $_POST['subTestResult'] = '';
     }


     $genericData = array(
          'vlsm_instance_id' => $instanceId,
          'vlsm_country_id' => $formId,
          'sample_reordered' => $_POST['sampleReordered'] ?? 'no',
          'external_sample_code' => (isset($_POST['serialNo']) && $_POST['serialNo'] != '' ? $_POST['serialNo'] : null),
          'facility_id' => (isset($_POST['facilityId']) && $_POST['facilityId'] != '') ? $_POST['facilityId'] : null,
          'sample_collection_date' => $_POST['sampleCollectionDate'],
          'sample_dispatched_datetime' => $_POST['sampleDispatchedDate'],
          'patient_gender' => (isset($_POST['gender']) && $_POST['gender'] != '') ? $_POST['gender'] : null,
          'patient_dob' => $_POST['dob'],
          'patient_age_in_years' => (isset($_POST['ageInYears']) && $_POST['ageInYears'] != '') ? $_POST['ageInYears'] : null,
          'patient_age_in_months' => (isset($_POST['ageInMonths']) && $_POST['ageInMonths'] != '') ? $_POST['ageInMonths'] : null,
          'is_patient_pregnant' => (isset($_POST['patientPregnant']) && $_POST['patientPregnant'] != '') ? $_POST['patientPregnant'] : null,
          'is_patient_breastfeeding' => (isset($_POST['breastfeeding']) && $_POST['breastfeeding'] != '') ? $_POST['breastfeeding'] : null,
          'pregnancy_trimester' => (isset($_POST['trimester']) && $_POST['trimester'] != '') ? $_POST['trimester'] : null,
          'patient_id' => (isset($_POST['artNo']) && $_POST['artNo'] != '') ? $_POST['artNo'] : null,
          'laboratory_number' => (isset($_POST['laboratoryNumber']) && $_POST['laboratoryNumber'] != '') ? $_POST['laboratoryNumber'] : null,
          'treatment_indication' => (isset($_POST['treatmentIndication']) && $_POST['treatmentIndication'] != '') ? $_POST['treatmentIndication'] : null,
          //'treatment_initiated_date'              => $_POST['dateOfArtInitiation'],
          'patient_mobile_number' => (isset($_POST['patientPhoneNumber']) && $_POST['patientPhoneNumber'] != '') ? $_POST['patientPhoneNumber'] : null,
          'consent_to_receive_sms' => (isset($_POST['receiveSms']) && $_POST['receiveSms'] != '') ? $_POST['receiveSms'] : null,
          'specimen_type' => (isset($_POST['specimenType']) && $_POST['specimenType'] != '') ? $_POST['specimenType'] : null,
          'request_clinician_name' => (isset($_POST['reqClinician']) && $_POST['reqClinician'] != '') ? $_POST['reqClinician'] : null,
          'request_clinician_phone_number' => (isset($_POST['reqClinicianPhoneNumber']) && $_POST['reqClinicianPhoneNumber'] != '') ? $_POST['reqClinicianPhoneNumber'] : null,
          'test_requested_on' => (isset($_POST['requestDate']) && $_POST['requestDate'] != '') ? DateUtility::isoDateFormat($_POST['requestDate']) : null,
          'testing_lab_focal_person' => (isset($_POST['vlFocalPerson']) && $_POST['vlFocalPerson'] != '') ? $_POST['vlFocalPerson'] : null,
          'testing_lab_focal_person_phone_number' => (isset($_POST['vlFocalPersonPhoneNumber']) && $_POST['vlFocalPersonPhoneNumber'] != '') ? $_POST['vlFocalPersonPhoneNumber'] : null,
          'lab_id' => (isset($_POST['labId']) && $_POST['labId'] != '') ? $_POST['labId'] : null,
          'test_platform' => $testingPlatform,
          'sample_received_at_hub_datetime' => $_POST['sampleReceivedAtHubOn'],
          'sample_received_at_testing_lab_datetime' => $_POST['sampleReceivedDate'],
          'sample_tested_datetime' => $_POST['sampleTestingDateAtLab'],
          'reason_for_testing' => (isset($_POST['reasonForTesting']) && $_POST['reasonForTesting'] != '') ? $_POST['reasonForTesting'] : null,
          'result_dispatched_datetime' => $_POST['resultDispatchedOn'],
          'is_sample_rejected' => (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] != '') ? $_POST['isSampleRejected'] : null,
          'reason_for_sample_rejection' => (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') ? $_POST['rejectionReason'] : null,
          'rejection_on' => (!empty($_POST['rejectionDate'])) ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
          'result' => $_POST['result'] ?? null,
          'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
          'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
          'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] : null,
          'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
          'result_approved_datetime' => (isset($_POST['approvedOn']) && $_POST['approvedOn'] != '') ? $_POST['approvedOn'] : null,
          'date_test_ordered_by_physician' => DateUtility::isoDateFormat($_POST['dateOfDemand'] ?? ''),
          'lab_tech_comments' => (isset($_POST['labComments']) && trim((string) $_POST['labComments']) != '') ? trim((string) $_POST['labComments']) : null,
          'funding_source' => (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') ? base64_decode((string) $_POST['fundingSource']) : null,
          'implementing_partner' => (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') ? base64_decode((string) $_POST['implementingPartner']) : null,
          'test_number' => (isset($_POST['viralLoadNo']) && $_POST['viralLoadNo'] != '') ? $_POST['viralLoadNo'] : null,
          'request_created_datetime' => DateUtility::getCurrentDateTime(),
          'last_modified_datetime' => DateUtility::getCurrentDateTime(),
          'manual_result_entry' => 'yes',
          'test_type' => $_POST['testType'],
          'sub_tests' => $_POST['subTestResult'],
          'test_type_form' => json_encode($_POST['dynamicFields']),
          'data_sync' => 0
     );

     // only if result status has changed, let us update
     if (!empty($resultStatus)) {
          $genericData['result_status'] = $resultStatus;
     }

     $genericData['last_modified_by'] = $_SESSION['userId'] ?? $_POST['userId'] ?? null;

     if ($_SESSION['instance']['type'] == 'remoteuser') {
          $genericData['remote_sample_code'] = (isset($_POST['sampleCode']) && $_POST['sampleCode'] != '') ? $_POST['sampleCode'] : null;
     } elseif ($_POST['sampleCodeCol'] != '') {
          $genericData['sample_code'] = (isset($_POST['sampleCodeCol']) && $_POST['sampleCodeCol'] != '') ? $_POST['sampleCodeCol'] : null;
     }

     //Update patient Information in Patients Table
     $patientsService->updatePatient($_POST, 'form_generic');


     if (isset($_POST['vlSampleId']) && $_POST['vlSampleId'] != '' && ($_POST['isSampleRejected'] == 'no' || $_POST['isSampleRejected'] == '')) {
          if (!empty($_POST['testName'])) {
               $finalResult = "";
               $db->where('generic_id', $_POST['vlSampleId']);
               $db->delete('generic_test_results');
               foreach ($_POST['testName'] as $subTestName => $subTests) {
                    foreach ($subTests as $testKey => $testKitName) {
                         if (!empty($testKitName)) {
                              $testData = array(
                                   'generic_id' => $_POST['vlSampleId'],
                                   'sub_test_name' => $subTestName,
                                   'result_type' => $_POST['resultType'][$subTestName],
                                   'test_name' => ($testKitName == 'other') ? $_POST['testNameOther'][$subTestName][$testKey] : $testKitName,
                                   'facility_id' => $_POST['labId'] ?? null,
                                   'sample_tested_datetime' => DateUtility::isoDateFormat($_POST['testDate'][$subTestName][$testKey] ?? '', true),
                                   'testing_platform' => $_POST['testingPlatform'][$subTestName][$testKey] ?? null,
                                   'kit_lot_no' => (str_contains((string)$testKitName, 'RDT')) ? $_POST['lotNo'][$subTestName][$testKey] : null,
                                   'kit_expiry_date' => (str_contains((string)$testKitName, 'RDT')) ? DateUtility::isoDateFormat($_POST['expDate'][$subTestName][$testKey]) : null,
                                   'result_unit' => $_POST['testResultUnit'][$subTestName][$testKey],
                                   'result' => $_POST['testResult'][$subTestName][$testKey],

                                   'final_result' => $_POST['finalResult'][$subTestName],
                                   'final_result_unit' => $_POST['finalTestResultUnit'][$subTestName],
                                   'final_result_interpretation' => $_POST['resultInterpretation'][$subTestName]
                              );
                              $db->insert('generic_test_results', $testData);
                              $finalResult = $_POST['finalResult'][$subTestName];
                         }
                    }
               }
               $genericData['result'] = $finalResult;
          }
     } else {
          $db->where('generic_id', $_POST['vlSampleId']);
          $db->delete('generic_test_results');
          $genericData['sample_tested_datetime'] = null;
     }

     $genericData['patient_first_name'] = $general->crypto('doNothing', $_POST['patientFirstName'], $genericData['patient_id']);
     $db->where('sample_id', $_POST['vlSampleId']);
     $id = $db->update($tableName, $genericData);
     error_log($db->getLastError());
     $patientId = (isset($_POST['artNo']) && $_POST['artNo'] != '') ? ' and patient id ' . $_POST['artNo'] : '';
     if ($id === true) {
          $_SESSION['alertMsg'] = _translate("Request updated successfully");
          //Add event log

          $eventType = 'update-lab-test-request';
          $action = $_SESSION['userName'] . ' updated request with the sample id ' . $_POST['sampleCode'] . $patientId;
          $resource = 'lab-test-request';

          $general->activityLog($eventType, $action, $resource);
     } else {
          $_SESSION['alertMsg'] = _translate("Please try again later");
     }
     header("Location:view-requests.php");
} catch (Exception $exc) {
     error_log($exc->getMessage());
     error_log($exc->getTraceAsString());
}
