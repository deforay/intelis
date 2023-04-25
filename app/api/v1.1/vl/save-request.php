<?php

use App\Services\ApiService;
use App\Services\CommonService;
use App\Services\UserService;
use App\Services\VlService;
use App\Utilities\DateUtils;

ini_set('memory_limit', -1);
session_unset(); // no need of session in json response
header('Content-Type: application/json');

$db = \MysqliDb::getInstance();

try {

    $general = new CommonService();
    $userDb = new UserService();
    $app = new ApiService();
    $vlModel = new VlService();

    $transactionId = $general->generateUUID();

    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $logVal = null;
    $absDecimalVal = null;
    $absVal = null;
    $txtVal = null;
    $finalResult = null;

    $origJson = file_get_contents("php://input") ?: '[]';
    $input = json_decode($origJson, true);

    if (empty($input) || empty($input['data'])) {
        throw new Exception("Invalid request");
    }

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $auth = $general->getHeader('Authorization');
    $authToken = str_replace("Bearer ", "", $auth);
    $user = $userDb->getUserFromToken($authToken);
    $roleUser = $userDb->getUserRole($user['user_id']);
    $responseData = [];
    $sQuery = "SELECT vlsm_instance_id FROM s_vlsm_instance";
    $rowData = $db->rawQuery($sQuery);
    $instanceId = $rowData[0]['vlsm_instance_id'];
    $formId = $general->getGlobalConfig('vl_form');

    $version = $general->getSystemConfig('sc_version');
    $deviceId = $general->getHeader('deviceId');

    foreach ($input['data'] as $rootKey => $data) {
        $sampleFrom = '';
        $data['formId'] = $data['countryId'] = $formId;
        $sampleFrom = '';
        /* V1 name to Id mapping */
        if (!is_numeric($data['provinceId'])) {
            $province = explode("##", $data['provinceId']);
            if (isset($province) && !empty($province)) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'geo_name', 'geographical_divisions', 'geo_id', true);
        }
        /* if (!is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (!is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        } */

        $data['api'] = "yes";
        $provinceCode = (isset($data['provinceCode']) && !empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (isset($data['provinceId']) && !empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = (isset($data['sampleCollectionDate']) && !empty($data['sampleCollectionDate'])) ? $data['sampleCollectionDate'] : null;

        if (empty($sampleCollectionDate)) {
            continue;
            //throw new Exception(_("Sample Collection Date is required"));
        }

        $update = "no";
        $rowData = false;
        $uniqueId = null;
        if (!empty($data['uniqueId']) || !empty($data['appSampleCode'])) {

            $sQuery = "SELECT vl_sample_id, unique_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_vl ";

            $sQueryWhere = [];

            if (isset($data['uniqueId']) && !empty($data['uniqueId'])) {
                $uniqueId = $data['uniqueId'];
                $sQueryWhere[] = " unique_id like '" . $data['uniqueId'] . "'";
            }
            if (isset($data['appSampleCode']) && !empty($data['appSampleCode'])) {
                $sQueryWhere[] = " app_sample_code like '" . $data['appSampleCode'] . "'";
            }

            if (!empty($sQueryWhere)) {
                $sQuery .= " WHERE " . implode(" AND ", $sQueryWhere);
            }

            $rowData = $db->rawQueryOne($sQuery);
            if ($rowData) {
                if($rowData['result_status'] == 7 || (isset($rowData['locked']) && $rowData['locked'] == 'yes')){
                    $payload = array(
                        'status' => 'failed',
                        'timestamp' => time(),
                        'error' => 'Unable to add this VL sample. Please try again later',
                        'data' => array()
                    );
                    $payload = json_encode($payload);
                    echo $payload;
                    exit(0);
                }
                $update = "yes";
                $uniqueId = $rowData['unique_id'];
                $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
                $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
                $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
            } else {
                $sampleJson = $vlModel->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
                $sampleData = json_decode($sampleJson, true);
            }
        } else {
            $sampleJson = $vlModel->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
            $sampleData = json_decode($sampleJson, true);
        }

        if (empty($uniqueId) || $uniqueId === 'undefined' || $uniqueId === 'null') {
            $uniqueId = $general->generateUUID();
        }

        if (!isset($data['countryId']) || $data['countryId'] == '') {
            $data['countryId'] = '';
        }

        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $data['sampleCollectionDate'] = DateUtils::isoDateFormat($data['sampleCollectionDate'], true);
        } else {
            $sampleCollectionDate = $data['sampleCollectionDate'] = null;
        }

        $data['instanceId'] = $data['instanceId'] ?: $instanceId;

        $vlData = array(
            'vlsm_country_id' => $data['formId'] ?? null,
            'unique_id' => $uniqueId,
            'sample_collection_date' => $data['sampleCollectionDate'],
            'vlsm_instance_id' => $data['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => $user['user_id'],
            'request_created_datetime' => (isset($data['createdOn']) && !empty($data['createdOn'])) ? DateUtils::isoDateFormat($data['createdOn'], true) : DateUtils::getCurrentDateTime(),
            'last_modified_by' => $user['user_id'],
            'last_modified_datetime' => (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? DateUtils::isoDateFormat($data['updatedOn'], true) : DateUtils::getCurrentDateTime()
        );

        if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
            $vlData['remote_sample_code'] = $sampleData['sampleCode'];
            $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
            $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
            $vlData['remote_sample'] = 'yes';
            $vlData['result_status'] = 9;

            if ($user['access_type'] === 'testing-lab') {
                $vlData['sample_code'] = $sampleData['sampleCode'];
                $vlData['result_status'] = 6;
            }
        } else {
            $vlData['sample_code'] = $sampleData['sampleCode'];
            $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $vlData['remote_sample'] = 'no';
            $vlData['result_status'] = 6;
        }

        $formAttributes = array(
            'applicationVersion'    => $version,
            'apiTransactionId'      => $transactionId,
            'mobileAppVersion'      => $input['appVersion'],
            'deviceId'              => $deviceId
        );
        $vlData['form_attributes'] = json_encode($formAttributes);


        $id = 0;
        if ($rowData) {
            $db = $db->where('vl_sample_id', $rowData['vl_sample_id']);
            $id = $db->update("form_vl", $vlData);
            $data['vlSampleId'] = $rowData['vl_sample_id'];
        } else {
            $id = $db->insert("form_vl", $vlData);
            $data['vlSampleId'] = $id;
        }
        $tableName = "form_vl";
        $tableName1 = "activity_log";


        if (empty(trim($data['sampleCode']))) {
            $data['sampleCode'] = null;
        }

        $status = 6;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = 9;
        }

        if (isset($data['approvedOnDateTime']) && trim($data['approvedOnDateTime']) != "") {
            $data['approvedOnDateTime'] = DateUtils::isoDateFormat($data['approvedOnDateTime'], true);
        } else {
            $data['approvedOnDateTime'] = null;
        }

        if (isset($data['reviewedOn']) && trim($data['reviewedOn']) != "") {
            $data['reviewedOn'] = DateUtils::isoDateFormat($data['reviewedOn'], true);
        } else {
            $data['reviewedOn'] = null;
        }

        if (isset($data['resultDispatchedOn']) && trim($data['resultDispatchedOn']) != "") {
            $data['resultDispatchedOn'] = DateUtils::isoDateFormat($data['resultDispatchedOn'], true);
        } else {
            $data['resultDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedOn']) && trim($data['sampleDispatchedOn']) != "") {
            $data['sampleDispatchedOn'] = DateUtils::isoDateFormat($data['sampleDispatchedOn'], true);
        } else {
            $data['sampleDispatchedOn'] = null;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $data['sampleReceivedDate'] = DateUtils::isoDateFormat($data['sampleReceivedDate'], true);
        } else {
            $data['sampleReceivedDate'] = null;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $data['sampleTestedDateTime'] = DateUtils::isoDateFormat($data['sampleTestedDateTime'], true);
        } else {
            $data['sampleTestedDateTime'] = null;
        }

        if (!empty($data['sampleTestingDateAtLab']) && trim($data['sampleTestingDateAtLab']) != "") {
            $data['sampleTestingDateAtLab'] = DateUtils::isoDateFormat($data['sampleTestingDateAtLab'], true);
        } else {
            $data['sampleTestingDateAtLab'] = null;
        }

        if (!empty($data['sampleReceivedAtHubOn']) && trim($data['sampleReceivedAtHubOn']) != "") {
            $data['sampleReceivedAtHubOn'] = DateUtils::isoDateFormat($data['sampleReceivedAtHubOn'], true);
        } else {
            $data['sampleReceivedAtHubOn'] = null;
        }

        if (isset($data['dateOfArtInitiation']) && trim($data['dateOfArtInitiation']) != "") {
            $data['dateOfArtInitiation'] = DateUtils::isoDateFormat($data['dateOfArtInitiation'], true);
        } else {
            $data['dateOfArtInitiation'] = null;
        }

        if (isset($data['patientDob']) && trim($data['patientDob']) != "") {
            $data['patientDob'] = DateUtils::isoDateFormat($data['patientDob'], false);
        } else {
            $data['patientDob'] = null;
        }

        if (isset($data['regimenInitiatedOn']) && trim($data['regimenInitiatedOn']) != "") {
            $data['regimenInitiatedOn'] = DateUtils::isoDateFormat($data['regimenInitiatedOn'], true);
        } else {
            $data['regimenInitiatedOn'] = null;
        }

        //Set Dispatched From Clinic To Lab Date
        if (isset($data['dateDispatchedFromClinicToLab']) && trim($data['dateDispatchedFromClinicToLab']) != "") {
            $data['dateDispatchedFromClinicToLab'] = DateUtils::isoDateFormat($data['dateDispatchedFromClinicToLab'], true);
        } else {
            $data['dateDispatchedFromClinicToLab'] = null;
        }

        if (isset($data['patientGender']) && trim($data['patientGender']) == 'male') {
            $data['patientPregnant'] = '';
            $data['breastfeeding'] = '';
        }

        if (isset($data['tnd']) && $data['tnd'] == 'yes' && $data['isSampleRejected'] == 'no') {
            $data['vlResult'] = 'Target Not Detected';
            $data['vlLog'] = '';
        }
        if (isset($data['bdl']) && $data['bdl'] == 'bdl'  && $data['isSampleRejected'] == 'no') {
            $data['vlResult'] = 'Below Detection Level';
            $data['vlLog'] = '';
        }

        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $finalResult = null;
            $status = 4;
        } else if (isset($data['vlResult']) && trim($data['vlResult']) != '') {
            if (in_array(strtolower($data['vlResult']), ['fail', 'failed', 'failure', 'error', 'err'])) {
                //Result is saved as entered
                $finalResult  = $data['vlResult'];
                $status = 5; // Invalid/Failed
            } else {

                $interpretedResults = $vlModel->interpretViralLoadResult($data['vlResult']);

                //Result is saved as entered
                $finalResult  = $data['vlResult'];
                $logVal = $interpretedResults['logVal'];
                $absDecimalVal = $interpretedResults['absDecimalVal'];
                $absVal = $interpretedResults['absVal'];
                $txtVal = $interpretedResults['txtVal'];
            }
            $status = 8;
            if (
                isset($globalConfig['vl_auto_approve_api_results']) &&
                $globalConfig['vl_auto_approve_api_results'] == "yes"
            ) {
                $status = 7;
            }
        }

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $data['revisedOn'] = DateUtils::isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = null;
        }
        $vlFulldata = array(
            'vlsm_instance_id'                      => $data['instanceId'],
            'vlsm_country_id'                       => $data['formId'],
            'unique_id'                             => $uniqueId,
            'app_sample_code'                       => $data['appSampleCode'] ?? null,
            'sample_reordered'                      => (isset($data['sampleReordered']) && $data['sampleReordered'] == 'yes') ? 'yes' :  'no',
            'sample_code_format'                    => (isset($data['sampleCodeFormat']) && $data['sampleCodeFormat'] != '') ? $data['sampleCodeFormat'] :  null,
            'facility_id'                           => (isset($data['facilityId']) && $data['facilityId'] != '') ? $data['facilityId'] :  null,
            'sample_collection_date'                => $data['sampleCollectionDate'],
            'patient_Gender'                        => (isset($data['patientGender']) && $data['patientGender'] != '') ? $data['patientGender'] :  null,
            'patient_dob'                           => $data['patientDob'],
            'patient_age_in_years'                  => (isset($data['ageInYears']) && $data['ageInYears'] != '') ? $data['ageInYears'] :  null,
            'patient_age_in_months'                 => (isset($data['ageInMonths']) && $data['ageInMonths'] != '') ? $data['ageInMonths'] :  null,
            'is_patient_pregnant'                   => (isset($data['patientPregnant']) && $data['patientPregnant'] != '') ? $data['patientPregnant'] :  null,
            'is_patient_breastfeeding'              => (isset($data['breastfeeding']) && $data['breastfeeding'] != '') ? $data['breastfeeding'] :  null,
            'patient_art_no'                        => (isset($data['patientArtNo']) && $data['patientArtNo'] != '') ? $data['patientArtNo'] :  null,
            'treatment_initiated_date'              => DateUtils::isoDateFormat($data['dateOfArtInitiation']),
            'reason_for_regimen_change'             => $data['reasonForArvRegimenChange'],
            'regimen_change_date'                   => DateUtils::isoDateFormat($data['dateOfArvRegimenChange']),
            'current_regimen'                       => (isset($data['artRegimen']) && $data['artRegimen'] != '') ? $data['artRegimen'] :  null,
            'date_of_initiation_of_current_regimen' => $data['regimenInitiatedOn'],
            'patient_mobile_number'                 => (isset($data['patientPhoneNumber']) && $data['patientPhoneNumber'] != '') ? $data['patientPhoneNumber'] :  null,
            'consent_to_receive_sms'                => (isset($data['receiveSms']) && $data['receiveSms'] != '') ? $data['receiveSms'] :  null,
            'sample_type'                           => (isset($data['specimenType']) && $data['specimenType'] != '') ? $data['specimenType'] :  null,
            'arv_adherance_percentage'              => (isset($data['arvAdherence']) && $data['arvAdherence'] != '') ? $data['arvAdherence'] :  null,
            'reason_for_vl_testing'                 => $data['reasonForVLTesting'] ?: $data['vlTestReason'] ?: null,
            'community_sample'                      => (isset($data['communitySample'])) ? $data['communitySample'] : null,
            'last_vl_date_routine'                  => (isset($data['rmTestingLastVLDate']) && $data['rmTestingLastVLDate'] != '') ? DateUtils::isoDateFormat($data['rmTestingLastVLDate']) :  null,
            'last_vl_result_routine'                => (isset($data['rmTestingVlValue']) && $data['rmTestingVlValue'] != '') ? $data['rmTestingVlValue'] :  null,
            'last_vl_date_failure_ac'               => (isset($data['repeatTestingLastVLDate']) && $data['repeatTestingLastVLDate'] != '') ? DateUtils::isoDateFormat($data['repeatTestingLastVLDate']) :  null,
            'last_vl_result_failure_ac'             => (isset($data['repeatTestingVlValue']) && $data['repeatTestingVlValue'] != '') ? $data['repeatTestingVlValue'] :  null,
            'last_vl_date_failure'                  => (isset($data['suspendTreatmentLastVLDate']) && $data['suspendTreatmentLastVLDate'] != '') ? DateUtils::isoDateFormat($data['suspendTreatmentLastVLDate']) :  null,
            'last_vl_result_failure'                => (isset($data['suspendTreatmentVlValue']) && $data['suspendTreatmentVlValue'] != '') ? $data['suspendTreatmentVlValue'] :  null,
            'request_clinician_name'                => (isset($data['reqClinician']) && $data['reqClinician'] != '') ? $data['reqClinician'] :  null,
            'request_clinician_phone_number'        => (isset($data['reqClinicianPhoneNumber']) && $data['reqClinicianPhoneNumber'] != '') ? $data['reqClinicianPhoneNumber'] :  null,
            'test_requested_on'                     => (isset($data['requestDate']) && $data['requestDate'] != '') ? DateUtils::isoDateFormat($data['requestDate']) :  null,
            'vl_focal_person'                       => (isset($data['vlFocalPerson']) && $data['vlFocalPerson'] != '') ? $data['vlFocalPerson'] :  null,
            'vl_focal_person_phone_number'          => (isset($data['vlFocalPersonPhoneNumber']) && $data['vlFocalPersonPhoneNumber'] != '') ? $data['vlFocalPersonPhoneNumber'] :  null,
            'lab_id'                                => (isset($data['labId']) && $data['labId'] != '') ? $data['labId'] :  null,
            'vl_test_platform'                      => (isset($data['testingPlatform']) && $data['testingPlatform'] != '') ? $data['testingPlatform'] :  null,
            'sample_received_at_hub_datetime'       => $data['sampleReceivedAtHubOn'],
            'sample_received_at_vl_lab_datetime'    => $data['sampleReceivedDate'],
            'sample_tested_datetime'                => $data['sampleTestingDateAtLab'],
            'sample_dispatched_datetime'            => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'            => $data['resultDispatchedOn'],
            'result_value_hiv_detection'            => (isset($data['hivDetection']) && $data['hivDetection'] != '') ? $data['hivDetection'] :  null,
            'reason_for_failure'                    => (isset($data['reasonForFailure']) && $data['reasonForFailure'] != '') ? $data['reasonForFailure'] :  null,
            'is_sample_rejected'                    => (isset($data['isSampleRejected']) && $data['isSampleRejected'] != '') ? $data['isSampleRejected'] : null,
            'reason_for_sample_rejection'           => (isset($data['rejectionReason']) && $data['rejectionReason'] != '') ? $data['rejectionReason'] :  null,
            'rejection_on'                          => (isset($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($data['rejectionDate']) : null,
            'result_value_absolute'                 => (isset($data['vlResult']) && !empty($data['vlResult']) && ($data['vlResult'] != 'Target Not Detected' && $data['vlResult'] != 'Below Detection Level')) ? $data['vlResult'] :  null,
            'result_value_absolute_decimal'         => (isset($data['vlResult']) && !empty($data['vlResult']) && ($data['vlResult'] != 'Target Not Detected' && $data['vlResult'] != 'Below Detection Level')) ? number_format((float)$data['vlResult'], 2, '.', '') :  null,
            'result'                                => $finalResult,
            'result_value_log'                      => (isset($data['vlLog']) && $data['vlLog'] != '') ? $data['vlLog'] :  null,
            'tested_by'                             => (isset($data['testedBy']) && $data['testedBy'] != '') ? $data['testedBy'] :  null,
            'result_approved_by'                    => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] :  null,
            'result_approved_datetime'              => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedOnDateTime'] :  null,
            'revised_by'                            => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                            => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : null,
            'reason_for_vl_result_changes'          => (isset($data['reasonForVlResultChanges']) && !empty($data['reasonForVlResultChanges'])) ? $data['reasonForVlResultChanges'] : null,
            'lab_tech_comments'                     => (isset($data['labComments']) && trim($data['labComments']) != '') ? trim($data['labComments']) :  null,
            'result_status'                         => $status,
            'funding_source'                        => (isset($data['fundingSource']) && trim($data['fundingSource']) != '') ? $data['fundingSource'] : null,
            'implementing_partner'                  => (isset($data['implementingPartner']) && trim($data['implementingPartner']) != '') ? $data['implementingPartner'] : null,
            'request_created_datetime'              => (isset($data['createdOn']) && !empty($data['createdOn'])) ? DateUtils::isoDateFormat($data['createdOn'], true) : DateUtils::getCurrentDateTime(),
            'last_modified_datetime'                => (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? DateUtils::isoDateFormat($data['updatedOn'], true) : DateUtils::getCurrentDateTime(),
            'manual_result_entry'                   => 'yes',
            'vl_result_category'                    => (isset($data['isSampleRejected']) && $data['isSampleRejected'] == 'yes') ? "rejected" : "",
            'external_sample_code'                  => $data['serialNo'] ?? null,
            'is_patient_new'                        => (isset($data['isPatientNew']) && $data['isPatientNew'] != '') ? $data['isPatientNew'] :  null,
            'has_patient_changed_regimen'           => (isset($data['hasChangedRegimen']) && $data['hasChangedRegimen'] != '') ? $data['hasChangedRegimen'] :  null,
            'sample_dispatched_datetime'    => (isset($data['dateDispatchedFromClinicToLab']) && $data['dateDispatchedFromClinicToLab'] != '') ? $data['specimenType'] :  null,
            'vl_test_number'                        => (isset($data['viralLoadNo'])) ? $data['viralLoadNo'] : null,
            'last_viral_load_result'                => (isset($data['lastViralLoadResult'])) ? $data['lastViralLoadResult'] : null,
            'last_viral_load_date'                  => (isset($data['lastViralLoadTestDate'])) ? DateUtils::isoDateFormat($data['lastViralLoadTestDate']) : null,
            'facility_support_partner'              => (isset($data['implementingPartner']) && $data['implementingPartner'] != '') ? $data['implementingPartner'] :  null,
            'date_test_ordered_by_physician'        => (isset($data['dateOfDemand']) && $data['dateOfDemand'] != '') ? $data['dateOfDemand'] :  null,
            'result_reviewed_by'                    => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : "",
            'result_reviewed_datetime'              => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'source_of_request'                     => $data['sourceOfRequest'] ?? "API"
        );



        if (isset($data['patientFirstName']) && $data['patientFirstName'] != "") {
            $vlFulldata['patient_first_name'] = $general->crypto('doNothing', $data['patientFirstName'], $vlFulldata['patient_art_no']);
        }
        if (isset($data['patientMiddleName']) && $data['patientMiddleName'] != "") {
            $vlFulldata['patient_middle_name'] = $general->crypto('doNothing', $data['patientMiddleName'], $vlFulldata['patient_art_no']);
        }
        if (isset($data['patientLastName']) && $data['patientLastName'] != "") {
            $vlFulldata['patient_last_name'] = $general->crypto('doNothing', $data['patientLastName'], $vlFulldata['patient_art_no']);
        }

        // South Sudan specific
        if ($formId === 1) {

            $patientFullName = [];
            if (trim($vlFulldata['patient_first_name']) != '') {
                $patientFullName[] = trim($vlFulldata['patient_first_name']);
            }
            if (trim($vlFulldata['patient_middle_name']) != '') {
                $patientFullName[] = trim($vlFulldata['patient_middle_name']);
            }
            if (trim($vlFulldata['patient_last_name']) != '') {
                $patientFullName[] = trim($vlFulldata['patient_last_name']);
            }

            if (!empty($patientFullName)) {
                $patientFullName = implode(" ", $patientFullName);
            } else {
                $patientFullName = '';
            }
            $vlFulldata['patient_first_name'] = $patientFullName;
            $vlFulldata['patient_middle_name'] = null;
            $vlFulldata['patient_last_name'] = null;
        }

        if ($rowData) {
            $vlFulldata['last_modified_datetime']  = (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? DateUtils::isoDateFormat($data['updatedOn'], true) : DateUtils::getCurrentDateTime();
            $vlFulldata['last_modified_by']  = $user['user_id'];
        } else {
            $vlFulldata['sample_registered_at_lab']  = DateUtils::getCurrentDateTime();
            $vlFulldata['request_created_by']  = $user['user_id'];
        }

        $vlFulldata['request_created_by'] =  $user['user_id'];
        $vlFulldata['last_modified_by'] =  $user['user_id'];

        $vlFulldata['vl_result_category'] = $vlModel->getVLResultCategory($vlFulldata['result_status'], $vlFulldata['result']);
        if ($vlFulldata['vl_result_category'] == 'failed' || $vlFulldata['vl_result_category'] == 'invalid') {
            $vlFulldata['result_status'] = 5;
        } elseif ($vlFulldata['vl_result_category'] == 'rejected') {
            $vlFulldata['result_status'] = 4;
        }
        //  echo " SAmple Id update :".$data['vlSampleId']; exit;
        //  echo '<pre>'; print_r($vlFulldata); 
        $id = 0;
        if (!empty($data['vlSampleId'])) {
            $db = $db->where('vl_sample_id', $data['vlSampleId']);
            $id = $db->update($tableName, $vlFulldata);
            error_log($db->getLastError());
            // echo "ID=>" . $id;
        }
        if ($id > 0) {
            $vlFulldata = $app->getTableDataUsingId($tableName, 'vl_sample_id', $data['vlSampleId']);
            $vlSampleCode = (isset($vlFulldata['sample_code']) && $vlFulldata['sample_code']) ? $vlFulldata['sample_code'] : $vlFulldata['remote_sample_code'];
            $responseData[$rootKey] = array(
                'status' => 'success',
                'sampleCode' => $vlSampleCode,
                'transactionId' => $transactionId,
                'uniqueId' => $vlFulldata['unique_id'],
                'appSampleCode' => (isset($data['appSampleCode']) && $data['appSampleCode'] != "") ? $vlFulldata['app_sample_code'] : null,
            );
            http_response_code(200);
        } else {
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'failed'
                );
            } else {
                $payload = array(
                    'status' => 'failed',
                    'timestamp' => time(),
                    'error' => 'Unable to add this VL sample. Please try again later',
                    'data' => array()
                );
            }
            http_response_code(301);
        }
    }
    if ($update == "yes") {
        $msg = 'Successfully updated';
    } else {
        $msg = 'Successfully added';
    }
    if (isset($responseData) && !empty($responseData)) {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'message' => $msg,
            'data'  => $responseData
        );
    } else {
        $payload = array(
            'status' => 'success',
            'timestamp' => time(),
            'message' => $msg
        );
    }

    http_response_code(200);
} catch (Exception $exc) {

    http_response_code(400);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}

$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], count($input['data']), 'save-request', 'vl', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
// exit(0); 
