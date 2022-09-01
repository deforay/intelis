<?php

ini_set('memory_limit', -1);
session_unset(); // no need of session in json response
header('Content-Type: application/json');

try {

    $general = new \Vlsm\Models\General();
    $userDb = new \Vlsm\Models\Users();
    $app = new \Vlsm\Models\App();
    $vlModel = new \Vlsm\Models\Vl();
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $user = null;
    $logVal = null;
    $absDecimalVal = null;
    $absVal = null;
    $txtVal = null;
    $finalResult = null;

    $origJson = file_get_contents("php://input") ?: '[]';
    $input = json_decode($origJson, true);

    if(empty($input) || empty($input['data'])) {
        throw new \Exception("Invalid request");
    }

    /* For API Tracking params */
    $requestUrl .= $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];

    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        /* Check if API token exists */
        $user = $userDb->getAuthToken($authToken);
    }

    // If authentication fails then do not proceed
    if (empty($user) || empty($user['user_id'])) {
        // $response = array(
        //     'status' => 'failed',
        //     'timestamp' => time(),
        //     'error' => 'Bearer Token Invalid',
        //     'data' => array()
        // );
        http_response_code(401);
        throw new \Exception(_("Bearer Token Invalid"));
    }
    $roleUser = $userDb->getUserRole($user['user_id']);
    $responseData = array();
    foreach ($input['data'] as $rootKey => $field) {
        $data = $field;
        $sampleFrom = '';
        $data['formId'] = $data['countryId'] = $general->getGlobalConfig('vl_form');
        $sQuery = "SELECT vlsm_instance_id FROM s_vlsm_instance";
        $rowData = $db->rawQuery($sQuery);
        $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
        $sampleFrom = '';
        /* V1 name to Id mapping */
        if (!is_numeric($data['provinceId'])) {
            $province = explode("##", $data['provinceId']);
            if (isset($province) && !empty($province)) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'province_name', 'province_details', 'province_id', true);
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

            $sQueryWhere = array();

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
            $data['sampleCollectionDate'] = $general->isoDateFormat($data['sampleCollectionDate'], true);
        } else {
            $sampleCollectionDate = $data['sampleCollectionDate'] = NULL;
        }
        $vlData = array(
            'vlsm_country_id' => $data['formId'] ?? null,
            'unique_id' => $uniqueId,
            'sample_collection_date' => $data['sampleCollectionDate'],
            'vlsm_instance_id' => $data['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => $user['user_id'],
            'request_created_datetime' => (isset($data['createdOn']) && !empty($data['createdOn'])) ? $general->isoDateFormat($data['createdOn'], true) : $general->getCurrentDateTime(),
            'last_modified_by' => $user['user_id'],
            'last_modified_datetime' => (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? $general->isoDateFormat($data['updatedOn'], true) : $general->getCurrentDateTime()
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
        $instanceId = '';
        if (empty($instanceId) && $data['instanceId']) {
            $instanceId = $data['instanceId'];
        }

        if (empty(trim($data['sampleCode']))) {
            $data['sampleCode'] = NULL;
        }

        $status = 6;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = 9;
        }

        if (isset($data['approvedOnDateTime']) && trim($data['approvedOnDateTime']) != "") {
            $data['approvedOnDateTime'] = $general->isoDateFormat($data['approvedOnDateTime'], true);
        } else {
            $data['approvedOnDateTime'] = NULL;
        }

        if (isset($data['reviewedOn']) && trim($data['reviewedOn']) != "") {
            $data['reviewedOn'] = $general->isoDateFormat($data['reviewedOn'], true);
        } else {
            $data['reviewedOn'] = NULL;
        }

        if (isset($data['resultDispatchedOn']) && trim($data['resultDispatchedOn']) != "") {
            $data['resultDispatchedOn'] = $general->isoDateFormat($data['resultDispatchedOn'], true);
        } else {
            $data['resultDispatchedOn'] = NULL;
        }

        if (isset($data['sampleDispatchedOn']) && trim($data['sampleDispatchedOn']) != "") {
            $data['sampleDispatchedOn'] = $general->isoDateFormat($data['sampleDispatchedOn'], true);
        } else {
            $data['sampleDispatchedOn'] = NULL;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $data['sampleReceivedDate'] = $general->isoDateFormat($data['sampleReceivedDate'], true);
        } else {
            $data['sampleReceivedDate'] = NULL;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $data['sampleTestedDateTime'] = $general->isoDateFormat($data['sampleTestedDateTime'], true);
        } else {
            $data['sampleTestedDateTime'] = NULL;
        }

        if (!empty($data['sampleTestingDateAtLab']) && trim($data['sampleTestingDateAtLab']) != "") {
            $data['sampleTestingDateAtLab'] = $general->isoDateFormat($data['sampleTestingDateAtLab'], true);
        } else {
            $data['sampleTestingDateAtLab'] = NULL;
        }

        if (!empty($data['sampleReceivedAtHubOn']) && trim($data['sampleReceivedAtHubOn']) != "") {
            $data['sampleReceivedAtHubOn'] = $general->isoDateFormat($data['sampleReceivedAtHubOn'], true);
        } else {
            $data['sampleReceivedAtHubOn'] = NULL;
        }

        if (isset($data['dateOfArtInitiation']) && trim($data['dateOfArtInitiation']) != "") {
            $data['dateOfArtInitiation'] = $general->isoDateFormat($data['dateOfArtInitiation'], true);
        } else {
            $data['dateOfArtInitiation'] = NULL;
        }

        if (isset($data['patientDob']) && trim($data['patientDob']) != "") {
            $data['patientDob'] = $general->isoDateFormat($data['patientDob'], false);
        } else {
            $data['patientDob'] = NULL;
        }

        if (isset($data['regimenInitiatedOn']) && trim($data['regimenInitiatedOn']) != "") {
            $data['regimenInitiatedOn'] = $general->isoDateFormat($data['regimenInitiatedOn'], true);
        } else {
            $data['regimenInitiatedOn'] = NULL;
        }

        //Set Dispatched From Clinic To Lab Date
        if (isset($data['dateDispatchedFromClinicToLab']) && trim($data['dateDispatchedFromClinicToLab']) != "") {
            $data['dateDispatchedFromClinicToLab'] = $general->isoDateFormat($data['dateDispatchedFromClinicToLab'], true);
        } else {
            $data['dateDispatchedFromClinicToLab'] = NULL;
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
            $data['revisedOn'] = $general->isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = NULL;
        }
        $vlFulldata = array(
            'vlsm_instance_id'                      => $instanceId,
            'vlsm_country_id'                       => $data['formId'],
            'unique_id'                             => $uniqueId,
            'app_sample_code'                       => isset($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'sample_code_title'                     => (isset($data['sampleCodeTitle']) && $data['sampleCodeTitle'] != '') ? $data['sampleCodeTitle'] :  'auto',
            'sample_reordered'                      => (isset($data['sampleReordered']) && $data['sampleReordered'] == 'yes') ? 'yes' :  'no',
            'sample_code_format'                    => (isset($data['sampleCodeFormat']) && $data['sampleCodeFormat'] != '') ? $data['sampleCodeFormat'] :  NULL,
            'facility_id'                           => (isset($data['facilityId']) && $data['facilityId'] != '') ? $data['facilityId'] :  NULL,
            'sample_collection_date'                => $data['sampleCollectionDate'],
            'patient_Gender'                        => (isset($data['patientGender']) && $data['patientGender'] != '') ? $data['patientGender'] :  NULL,
            'patient_dob'                           => $data['patientDob'],
            'patient_age_in_years'                  => (isset($data['ageInYears']) && $data['ageInYears'] != '') ? $data['ageInYears'] :  NULL,
            'patient_age_in_months'                 => (isset($data['ageInMonths']) && $data['ageInMonths'] != '') ? $data['ageInMonths'] :  NULL,
            'is_patient_pregnant'                   => (isset($data['patientPregnant']) && $data['patientPregnant'] != '') ? $data['patientPregnant'] :  NULL,
            'is_patient_breastfeeding'              => (isset($data['breastfeeding']) && $data['breastfeeding'] != '') ? $data['breastfeeding'] :  NULL,
            'patient_art_no'                        => (isset($data['patientArtNo']) && $data['patientArtNo'] != '') ? $data['patientArtNo'] :  NULL,
            'treatment_initiated_date'              => $general->isoDateFormat($data['dateOfArtInitiation']),
            'reason_for_regimen_change'             => $data['reasonForArvRegimenChange'],
            'regimen_change_date'                   => $general->isoDateFormat($data['dateOfArvRegimenChange']),
            'current_regimen'                       => (isset($data['artRegimen']) && $data['artRegimen'] != '') ? $data['artRegimen'] :  NULL,
            'date_of_initiation_of_current_regimen' => $data['regimenInitiatedOn'],
            'patient_mobile_number'                 => (isset($data['patientPhoneNumber']) && $data['patientPhoneNumber'] != '') ? $data['patientPhoneNumber'] :  NULL,
            'consent_to_receive_sms'                => (isset($data['receiveSms']) && $data['receiveSms'] != '') ? $data['receiveSms'] :  NULL,
            'sample_type'                           => (isset($data['specimenType']) && $data['specimenType'] != '') ? $data['specimenType'] :  NULL,
            'arv_adherance_percentage'              => (isset($data['arvAdherence']) && $data['arvAdherence'] != '') ? $data['arvAdherence'] :  NULL,
            'reason_for_vl_testing'                 => (isset($data['stViralTesting'])) ? $data['stViralTesting'] : NULL,
            'community_sample'                      => (isset($data['communitySample'])) ? $data['communitySample'] : NULL,
            'last_vl_date_routine'                  => (isset($data['rmTestingLastVLDate']) && $data['rmTestingLastVLDate'] != '') ? $general->isoDateFormat($data['rmTestingLastVLDate']) :  NULL,
            'last_vl_result_routine'                => (isset($data['rmTestingVlValue']) && $data['rmTestingVlValue'] != '') ? $data['rmTestingVlValue'] :  NULL,
            'last_vl_date_failure_ac'               => (isset($data['repeatTestingLastVLDate']) && $data['repeatTestingLastVLDate'] != '') ? $general->isoDateFormat($data['repeatTestingLastVLDate']) :  NULL,
            'last_vl_result_failure_ac'             => (isset($data['repeatTestingVlValue']) && $data['repeatTestingVlValue'] != '') ? $data['repeatTestingVlValue'] :  NULL,
            'last_vl_date_failure'                  => (isset($data['suspendTreatmentLastVLDate']) && $data['suspendTreatmentLastVLDate'] != '') ? $general->isoDateFormat($data['suspendTreatmentLastVLDate']) :  NULL,
            'last_vl_result_failure'                => (isset($data['suspendTreatmentVlValue']) && $data['suspendTreatmentVlValue'] != '') ? $data['suspendTreatmentVlValue'] :  NULL,
            'request_clinician_name'                => (isset($data['reqClinician']) && $data['reqClinician'] != '') ? $data['reqClinician'] :  NULL,
            'request_clinician_phone_number'        => (isset($data['reqClinicianPhoneNumber']) && $data['reqClinicianPhoneNumber'] != '') ? $data['reqClinicianPhoneNumber'] :  NULL,
            'test_requested_on'                     => (isset($data['requestDate']) && $data['requestDate'] != '') ? $general->isoDateFormat($data['requestDate']) :  NULL,
            'vl_focal_person'                       => (isset($data['vlFocalPerson']) && $data['vlFocalPerson'] != '') ? $data['vlFocalPerson'] :  NULL,
            'vl_focal_person_phone_number'          => (isset($data['vlFocalPersonPhoneNumber']) && $data['vlFocalPersonPhoneNumber'] != '') ? $data['vlFocalPersonPhoneNumber'] :  NULL,
            'lab_id'                                => (isset($data['labId']) && $data['labId'] != '') ? $data['labId'] :  NULL,
            'vl_test_platform'                      => (isset($data['testingPlatform']) && $data['testingPlatform'] != '') ? $data['testingPlatform'] :  NULL,
            'sample_received_at_hub_datetime'       => $data['sampleReceivedAtHubOn'],
            'sample_received_at_vl_lab_datetime'    => $data['sampleReceivedDate'],
            'sample_tested_datetime'                => $data['sampleTestingDateAtLab'],
            'sample_dispatched_datetime'            => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'            => $data['resultDispatchedOn'],
            'result_value_hiv_detection'            => (isset($data['hivDetection']) && $data['hivDetection'] != '') ? $data['hivDetection'] :  NULL,
            'reason_for_failure'                    => (isset($data['reasonForFailure']) && $data['reasonForFailure'] != '') ? $data['reasonForFailure'] :  NULL,
            'is_sample_rejected'                    => (isset($data['isSampleRejected']) && $data['isSampleRejected'] != '') ? $data['isSampleRejected'] : NULL,
            'reason_for_sample_rejection'           => (isset($data['rejectionReason']) && $data['rejectionReason'] != '') ? $data['rejectionReason'] :  NULL,
            'rejection_on'                          => (isset($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? $general->isoDateFormat($data['rejectionDate']) : null,
            'result_value_absolute'                 => (isset($data['vlResult']) && !empty($data['vlResult']) && ($data['vlResult'] != 'Target Not Detected' && $data['vlResult'] != 'Below Detection Level')) ? $data['vlResult'] :  NULL,
            'result_value_absolute_decimal'         => (isset($data['vlResult']) && !empty($data['vlResult']) && ($data['vlResult'] != 'Target Not Detected' && $data['vlResult'] != 'Below Detection Level')) ? number_format((float)$data['vlResult'], 2, '.', '') :  NULL,
            'result'                                => $finalResult,
            'result_value_log'                      => (isset($data['vlLog']) && $data['vlLog'] != '') ? $data['vlLog'] :  NULL,
            'tested_by'                             => (isset($data['testedBy']) && $data['testedBy'] != '') ? $data['testedBy'] :  NULL,
            'result_approved_by'                    => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] :  NULL,
            'result_approved_datetime'              => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedOnDateTime'] :  NULL,
            'revised_by'                            => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                            => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : "",
            'reason_for_vl_result_changes'          => (!empty($data['reasonForVlResultChanges']) && !empty($data['reasonForVlResultChanges'])) ? $data['reasonForVlResultChanges'] : null,
            'lab_tech_comments'                     => (isset($data['labComments']) && trim($data['labComments']) != '') ? trim($data['labComments']) :  NULL,
            'result_status'                         => $status,
            'funding_source'                        => (isset($data['fundingSource']) && trim($data['fundingSource']) != '') ? $data['fundingSource'] : NULL,
            'implementing_partner'                  => (isset($data['implementingPartner']) && trim($data['implementingPartner']) != '') ? $data['implementingPartner'] : NULL,
            'request_created_datetime'              => (isset($data['createdOn']) && !empty($data['createdOn'])) ? $general->isoDateFormat($data['createdOn'], true) : $general->getCurrentDateTime(),
            'last_modified_datetime'                => (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? $general->isoDateFormat($data['updatedOn'], true) : $general->getCurrentDateTime(),
            'manual_result_entry'                   => 'yes',
            'vl_result_category'                    => (isset($data['isSampleRejected']) && $data['isSampleRejected'] == 'yes') ? "rejected" : "",
            'external_sample_code'                  => isset($data['serialNo']) ? $data['serialNo'] : null,
            'is_patient_new'                        => (isset($data['isPatientNew']) && $data['isPatientNew'] != '') ? $data['isPatientNew'] :  NULL,
            'has_patient_changed_regimen'           => (isset($data['hasChangedRegimen']) && $data['hasChangedRegimen'] != '') ? $data['hasChangedRegimen'] :  NULL,
            'date_dispatched_from_clinic_to_lab'    => (isset($data['dateDispatchedFromClinicToLab']) && $data['dateDispatchedFromClinicToLab'] != '') ? $data['specimenType'] :  NULL,
            'vl_test_number'                        => (isset($data['viralLoadNo'])) ? $data['viralLoadNo'] : NULL,
            'last_viral_load_result'                => (isset($data['lastViralLoadResult'])) ? $data['lastViralLoadResult'] : NULL,
            'last_viral_load_date'                  => (isset($data['lastViralLoadTestDate'])) ? $data['lastViralLoadTestDate'] : NULL,
            'facility_support_partner'              => (isset($data['implementingPartner']) && $data['implementingPartner'] != '') ? $data['implementingPartner'] :  NULL,
            'date_test_ordered_by_physician'        => (isset($data['dateOfDemand']) && $data['dateOfDemand'] != '') ? $data['dateOfDemand'] :  NULL,
            'result_reviewed_by'                    => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : "",
            'result_reviewed_datetime'              => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'source_of_request'                     => "app"
        );



        if (isset($data['patientFullName']) && $data['patientFullName'] != "") {
            $vlFulldata['patient_first_name'] = $general->crypto('encrypt', $data['patientFullName'], $vlFulldata['patient_art_no']);
        }
        if (isset($data['patientMiddleName']) && $data['patientMiddleName'] != "") {
            $vlFulldata['patient_middle_name'] = $general->crypto('encrypt', $data['patientMiddleName'], $vlFulldata['patient_art_no']);
        }
        if (isset($data['patientLastName']) && $data['patientLastName'] != "") {
            $vlFulldata['patient_last_name'] = $general->crypto('encrypt', $data['patientLastName'], $vlFulldata['patient_art_no']);
        }
        if ($rowData) {
            $vlFulldata['last_modified_datetime']  = (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? $general->isoDateFormat($data['updatedOn'], true) : $general->getCurrentDateTime();
            $vlFulldata['last_modified_by']  = $user['user_id'];
        } else {
            $vlFulldata['sample_registered_at_lab']  = $general->getCurrentDateTime();
            $vlFulldata['request_created_by']  = $user['user_id'];
        }

        $vlFulldata['request_created_by'] =  $user['user_id'];
        $vlFulldata['last_modified_by'] =  $user['user_id'];

        $vlFulldata['vl_result_category'] = $vlModel->getVLResultCategory($vlFulldata['result_status'], $vlFulldata['result']);

        $id = 0;
        if (!empty($data['vlSampleId'])) {
            $db = $db->where('vl_sample_id', $data['vlSampleId']);
            $id = $db->update($tableName, $vlFulldata);
            // echo "ID=>" . $id;
        }
        if ($id > 0) {
            $vlFulldata = $app->getTableDataUsingId($tableName, 'vl_sample_id', $data['vlSampleId']);
            $vlSampleCode = (isset($vlFulldata['sample_code']) && $vlFulldata['sample_code']) ? $vlFulldata['sample_code'] : $vlFulldata['remote_sample_code'];
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $vlSampleCode,
                    'uniqueId' => $vlFulldata['unique_id'],
                    'appSampleCode' => $vlFulldata['app_sample_code'],
                );
            } else {
                $responseData[$rootKey] = array(
                    'sampleCode' => $vlSampleCode,
                    'uniqueId' => $vlFulldata['unique_id'],
                    'appSampleCode' => $vlFulldata['app_sample_code'],
                );
            }
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

    if (isset($user['token_updated']) && $user['token_updated'] === true) {
        $payload['token'] = $user['new_token'];
    } else {
        $payload['token'] = null;
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
$general->addApiTracking($user['user_id'], count($input['data']), 'save-request', 'vl', $requestUrl, $origJson, $payload, 'json');
echo $payload;
exit(0);
