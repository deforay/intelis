<?php

session_unset(); // no need of session in json response
try {
    ini_set('memory_limit', -1);
    header('Content-Type: application/json');
    $general = new \Vlsm\Models\General();
    $userDb = new \Vlsm\Models\Users();
    $app = new \Vlsm\Models\App();
    $eidModel = new \Vlsm\Models\Eid();
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $user = null;

    $input = json_decode(file_get_contents("php://input"), true);

    /* For API Tracking params */
    $requestUrl .= $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $params = file_get_contents("php://input");

    $auth = $general->getHeader('Authorization');
    if (!empty($auth)) {
        $authToken = str_replace("Bearer ", "", $auth);
        /* Check if API token exists */
        $user = $userDb->getAuthToken($authToken);
    }

    // If authentication fails then do not proceed
    if (empty($user) || empty($user['user_id'])) {
        $response = array(
            'status' => 'failed',
            'timestamp' => time(),
            'error' => 'Bearer Token Invalid',
            'data' => array()
        );
        http_response_code(401);
        echo json_encode($response);
        exit(0);
    }
    $roleUser = $userDb->getUserRole($user['user_id']);
    $responseData = array();
    foreach ($input['data'] as $rootKey => $field) {
        $data = $field;
        $sampleFrom = '';
        $data['formId'] = $data['countryId'] = $general->getGlobalConfig('vl_form');
        $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
        $rowData = $db->rawQuery($sQuery);
        $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
        $sampleFrom = '';
        /* V1 name to Id mapping */
        if (!is_numeric($data['provinceId'])) {
            $province = explode("##", $data['provinceId']);
            if (isset($province) && count($province) > 0) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'province_name', 'province_details', 'province_id', true);
        }
        if (!is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (!is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }

        $data['api'] = "yes";

        $provinceCode = (isset($data['provinceCode']) && !empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (isset($data['provinceId']) && !empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = (isset($data['sampleCollectionDate']) && !empty($data['sampleCollectionDate'])) ? $data['sampleCollectionDate'] : null;

        if (empty($sampleCollectionDate)) {
            exit();
        }
        $update = "no";
        $rowData = false;

        if ((isset($data['sampleCode']) && !empty($data['sampleCode'])) || (isset($data['remoteSampleCode']) && !empty($data['uniqueId'])) || (isset($data['uniqueId']) && !empty($data['uniqueId']))) {
            $sQuery = "SELECT eid_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_eid ";
            $sWhere = array();
            if (isset($data['uniqueId']) && !empty($data['uniqueId'])) {
                $sWhere[] = " unique_id like '" . $data['uniqueId'] . "'";
            } else if (isset($data['sampleCode']) && !empty($data['sampleCode'])) {
                $sWhere[] = " sample_code like '" . $data['sampleCode'] . "'";
            } else if (isset($data['remoteSampleCode']) != "" && !empty($data['remoteSampleCode'])) {
                $sWhere[] = " remote_sample_code like '" . $data['sampleCode'] . "'";
            }
            if (isset($sWhere) && count($sWhere) > 0) {
                $sQuery .= " WHERE " . implode(" AND ", $sWhere) . " limit 1";
            } else {
                $sQuery .= "limit 1";
            }
            $rowData = $db->rawQueryOne($sQuery);
            if ($rowData) {
                $update = "yes";
                if ($user['access_type'] != 'testing-lab') {
                    $sampleData['sampleCode'] = (!empty($rowData['remote_sample_code'])) ? $rowData['remote_sample_code'] : null;
                    $sampleData['sampleCodeFormat'] = (!empty($rowData['remote_sample_code_format'])) ? $rowData['remote_sample_code_format'] : null;
                    $sampleData['sampleCodeKey'] = (!empty($rowData['remote_sample_code_key'])) ? $rowData['remote_sample_code_key'] : null;
                } else {
                    $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : null;
                    $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : null;
                    $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : null;
                }
            } else {
                $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
                $sampleData = json_decode($sampleJson, true);
            }
        } else {
            $sampleJson = $eidModel->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
            $sampleData = json_decode($sampleJson, true);
        }
        if (!isset($data['countryId']) || $data['countryId'] == '') {
            $data['countryId'] = '';
        }
        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $sampleCollectionDate = explode(" ", $data['sampleCollectionDate']);
            $data['sampleCollectionDate'] = $general->isoDateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
        } else {
            $data['sampleCollectionDate'] = NULL;
        }
        $eidData = array(
            'vlsm_country_id' => $data['countryId'],
            'sample_collection_date' => $data['sampleCollectionDate'],
            'vlsm_instance_id' => $data['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => $user['user_id'],
            'request_created_datetime' => $general->getCurrentDateTime(),
            'last_modified_by' => $user['user_id'],
            'last_modified_datetime' => $general->getCurrentDateTime()
        );

        if ($user['access_type'] != 'testing-lab') {
            $eidData['remote_sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $eidData['remote_sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $eidData['remote_sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $eidData['remote_sample'] = 'yes';
            $eidData['result_status'] = 9;
            /* if ($roleUser['access_type'] == 'testing-lab') {
                $eidData['sample_code'] = !empty($data['appSampleCode']) ? $data['appSampleCode'] : null;
            } */
        } else {
            $eidData['sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $eidData['sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $eidData['sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $eidData['remote_sample'] = 'no';
            $eidData['result_status'] = 6;
        }
        /* echo "<pre>";
        print_r($eidData); 
        die;*/
        $id = 0;
        if (isset($rowData) && $rowData['eid_id'] > 0) {
            $db = $db->where('eid_id', $rowData['eid_id']);
            $id = $db->update("form_eid", $eidData);
            $data['eidSampleId'] = $rowData['eid_id'];
        } else {
            $id = $db->insert("form_eid", $eidData);
            $data['eidSampleId'] = $id;
        }
        /* print_r($db->getLastError());
        die; */
        $tableName = "form_eid";
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

        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $data['result'] = null;
            $status = 4;
        } else if (
            isset($globalConfig['eid_auto_approve_api_results']) &&
            $globalConfig['eid_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (isset($data['result']) && !empty($data['result']))
        ) {
            $status = 7;
        } else if ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (isset($data['result']) && !empty($data['result']))) {
            $status = 8;
        }

        if (isset($data['approvedOn']) && trim($data['approvedOn']) != "") {
            $approvedOn = explode(" ", $data['approvedOn']);
            $data['approvedOn'] = $general->isoDateFormat($approvedOn[0]) . " " . $approvedOn[1];
        } else {
            $data['approvedOn'] = NULL;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $sampleReceivedDate = explode(" ", $data['sampleReceivedDate']);
            $data['sampleReceivedDate'] = $general->isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
        } else {
            $data['sampleReceivedDate'] = NULL;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $sampleTestedDate = explode(" ", $data['sampleTestedDateTime']);
            $data['sampleTestedDateTime'] = $general->isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
        } else {
            $data['sampleTestedDateTime'] = NULL;
        }

        if (isset($data['rapidtestDate']) && trim($data['rapidtestDate']) != "") {
            $data['rapidtestDate'] = $general->isoDateFormat($data['rapidtestDate']);
        } else {
            $data['rapidtestDate'] = NULL;
        }

        if (isset($data['childDob']) && trim($data['childDob']) != "") {
            $data['childDob'] = $general->isoDateFormat($data['childDob']);
        } else {
            $data['childDob'] = NULL;
        }

        if (isset($data['mothersDob']) && trim($data['mothersDob']) != "") {
            $data['mothersDob'] = $general->isoDateFormat($data['mothersDob']);
        } else {
            $data['mothersDob'] = NULL;
        }


        if (isset($data['motherTreatmentInitiationDate']) && trim($data['motherTreatmentInitiationDate']) != "") {
            $data['motherTreatmentInitiationDate'] = $general->isoDateFormat($data['motherTreatmentInitiationDate']);
        } else {
            $data['motherTreatmentInitiationDate'] = NULL;
        }

        if (isset($data['previousPCRTestDate']) && trim($data['previousPCRTestDate']) != "") {
            $previousPCRTestDate = explode(" ", $data['previousPCRTestDate']);
            $data['previousPCRTestDate'] = $general->isoDateFormat($previousPCRTestDate[0]) . " " . $previousPCRTestDate[1];
        } else {
            $data['previousPCRTestDate'] = NULL;
        }

        if (isset($data['motherViralLoadCopiesPerMl']) && $data['motherViralLoadCopiesPerMl'] != "") {
            $motherVlResult = $data['motherViralLoadCopiesPerMl'];
        } else if (isset($data['motherViralLoadText']) && $data['motherViralLoadText'] != "") {
            $motherVlResult = $data['motherViralLoadText'];
        } else {
            $motherVlResult = null;
        }
        if (isset($data['reviewedOn']) && trim($data['reviewedOn']) != "") {
            $data['reviewedOn'] = $general->isoDateFormat($data['reviewedOn']);
        } else {
            $data['reviewedOn'] = NULL;
        }

        if (isset($data['resultDispatchedOn']) && trim($data['resultDispatchedOn']) != "") {
            $resultDispatchedOn = explode(" ", $data['resultDispatchedOn']);
            $data['resultDispatchedOn'] = $general->isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
        } else {
            $data['resultDispatchedOn'] = NULL;
        }

        if (isset($data['sampleDispatchedOn']) && trim($data['sampleDispatchedOn']) != "") {
            $sampleDispatchedOn = explode(" ", $data['sampleDispatchedOn']);
            $data['sampleDispatchedOn'] = $general->isoDateFormat($sampleDispatchedOn[0]) . " " . $sampleDispatchedOn[1];
        } else {
            $data['sampleDispatchedOn'] = NULL;
        }

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $data['revisedOn'] = $general->isoDateFormat($data['revisedOn']);
        } else {
            $data['revisedOn'] = NULL;
        }

        $eidData = array(
            'vlsm_instance_id'                                  => $instanceId,
            'vlsm_country_id'                                   => $data['formId'],
            'unique_id'                                         => isset($data['uniqueId']) ? $data['uniqueId'] : null,
            'app_sample_code'                                   => isset($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'facility_id'                                       => isset($data['facilityId']) ? $data['facilityId'] : null,
            'province_id'                                       => isset($data['provinceId']) ? $data['provinceId'] : null,
            'lab_id'                                            => isset($data['labId']) ? $data['labId'] : null,
            'implementing_partner'                              => isset($data['implementingPartner']) ? $data['implementingPartner'] : null,
            'funding_source'                                    => isset($data['fundingSource']) ? $data['fundingSource'] : null,
            'mother_id'                                         => isset($data['mothersId']) ? $data['mothersId'] : null,
            'caretaker_contact_consent'                         => isset($data['caretakerConsentForContact']) ? $data['caretakerConsentForContact'] : null,
            'caretaker_phone_number'                            => isset($data['caretakerPhoneNumber']) ? $data['caretakerPhoneNumber'] : null,
            'caretaker_address'                                 => isset($data['caretakerAddress']) ? $data['caretakerAddress'] : null,
            'mother_name'                                       => isset($data['mothersName']) ? $data['mothersName'] : null,
            'mother_dob'                                        => isset($data['mothersDob']) ? $data['mothersDob'] : null,
            'mother_marital_status'                             => isset($data['mothersMaritalStatus']) ? $data['mothersMaritalStatus'] : null,
            'mother_treatment'                                  => isset($data['motherTreatment']) ? implode(",", $data['motherTreatment']) : null,
            'mother_treatment_other'                            => isset($data['motherTreatmentOther']) ? $data['motherTreatmentOther'] : null,
            'mother_treatment_initiation_date'                  => isset($data['motherTreatmentInitiationDate']) ? $data['motherTreatmentInitiationDate'] : null,
            'child_id'                                          => isset($data['childId']) ? $data['childId'] : null,
            'child_name'                                        => isset($data['childName']) ? $data['childName'] : null,
            'child_surname'                                     => isset($data['childSurName']) ? $data['childSurName'] : null,
            'child_dob'                                         => isset($data['childDob']) ? $data['childDob'] : null,
            'child_gender'                                      => isset($data['childGender']) ? $data['childGender'] : null,
            'child_age'                                         => isset($data['childAge']) ? $data['childAge'] : null,
            'child_treatment'                                   => isset($data['childTreatment']) ? implode(",", $data['childTreatment']) : null,
            'child_treatment_other'                             => isset($data['childTreatmentOther']) ? implode(",", $data['childTreatmentOther']) : null,
            'mother_cd4'                                        => isset($data['mothercd4']) ? $data['mothercd4'] : null,
            'mother_vl_result'                                  => $motherVlResult,
            'mother_hiv_status'                                 => isset($data['mothersHIVStatus']) ? $data['mothersHIVStatus'] : null,
            'pcr_test_performed_before'                         => isset($data['pcrTestPerformedBefore']) ? $data['pcrTestPerformedBefore'] : null,
            'previous_pcr_result'                               => isset($data['prePcrTestResult']) ? $data['prePcrTestResult'] : null,
            'last_pcr_date'                                     => isset($data['previousPCRTestDate']) ? $data['previousPCRTestDate'] : null,
            'reason_for_pcr'                                    => isset($data['pcrTestReason']) ? $data['pcrTestReason'] : null,
            'community_sample'                                  => (isset($data['communitySample'])) ? $data['communitySample'] : NULL,
            'has_infant_stopped_breastfeeding'                  => isset($data['hasInfantStoppedBreastfeeding']) ? $data['hasInfantStoppedBreastfeeding'] : null,
            'age_breastfeeding_stopped_in_months'               => isset($data['ageBreastfeedingStopped']) ? $data['ageBreastfeedingStopped'] : null,
            'choice_of_feeding'                                 => isset($data['choiceOfFeeding']) ? $data['choiceOfFeeding'] : null,
            'is_cotrimoxazole_being_administered_to_the_infant' => isset($data['isCotrimoxazoleBeingAdministered']) ? $data['isCotrimoxazoleBeingAdministered'] : null,
            'specimen_type'                                     => isset($data['specimenType']) ? $data['specimenType'] : null,
            'sample_collection_date'                            => isset($data['sampleCollectionDate']) ? $data['sampleCollectionDate'] : null,
            'sample_dispatched_datetime'                        => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'                        => $data['resultDispatchedOn'],
            'sample_requestor_phone'                            => isset($data['sampleRequestorPhone']) ? $data['sampleRequestorPhone'] : null,
            'sample_requestor_name'                             => isset($data['sampleRequestorName']) ? $data['sampleRequestorName'] : null,
            'rapid_test_performed'                              => isset($data['rapidTestPerformed']) ? $data['rapidTestPerformed'] : null,
            'rapid_test_date'                                   => isset($data['rapidtestDate']) ? $data['rapidtestDate'] : null,
            'rapid_test_result'                                 => isset($data['rapidTestResult']) ? $data['rapidTestResult'] : null,
            'lab_reception_person'                              => isset($data['labReceptionPerson']) ? $data['labReceptionPerson'] : null,
            'sample_received_at_vl_lab_datetime'                => isset($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
            'eid_test_platform'                                 => isset($data['eidPlatform']) ? $data['eidPlatform'] : null,
            'import_machine_name'                               => isset($data['machineName']) ? $data['machineName'] : null,
            'sample_tested_datetime'                            => isset($data['sampleTestedDateTime']) ? $data['sampleTestedDateTime'] : null,
            'is_sample_rejected'                                => isset($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
            'result'                                            => isset($data['result']) ? $data['result'] : null,
            'tested_by'                                         => (isset($data['testedBy']) && $data['testedBy'] != '') ? $data['testedBy'] :  $user['user_id'],
            'result_approved_by'                                => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] :  NULL,
            'result_approved_datetime'                          => (isset($data['approvedOn']) && $data['approvedOn'] != '') ? $data['approvedOn'] :  NULL,
            'lab_tech_comments'                                 => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'result_reviewed_by'                                => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : null,
            'result_reviewed_datetime'                          => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'revised_by'                                        => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                                        => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : "",
            'reason_for_changing'                               => (!empty($data['reasonForEidResultChanges']) && !empty($data['reasonForEidResultChanges'])) ? $data['reasonForEidResultChanges'] : null,
            'result_status'                                     => $status,
            'data_sync'                                         => 0,
            'reason_for_sample_rejection'                       => isset($data['sampleRejectionReason']) ? $data['sampleRejectionReason'] : null,
            'rejection_on'                                      => (isset($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? $general->isoDateFormat($data['rejectionDate']) : null,
            'source_of_request'                                 => "app"
        );

        if ($rowData) {
            $eidData['last_modified_datetime']  = $general->getCurrentDateTime();
            $eidData['last_modified_by']  = $user['user_id'];
        } else {
            $eidData['sample_registered_at_lab']  = $general->getCurrentDateTime();
            $eidData['request_created_by']  = $user['user_id'];
        }

        $eidData['request_created_by'] =  $user['user_id'];
        $eidData['last_modified_by'] =  $user['user_id'];

        /* echo "<pre>";
        print_r($eidData);
        die; */
        $id = 0;
        if (!empty($data['eidSampleId'])) {
            $db = $db->where('eid_id', $data['eidSampleId']);
            $id = $db->update($tableName, $eidData);
        }
        if ($id > 0) {
            $eidData = $app->getTableDataUsingId($tableName, 'eid_id', $data['eidSampleId']);
            $eidSampleCode = (isset($eidData['sample_code']) && $eidData['sample_code']) ? $eidData['sample_code'] : $eidData['remote_sample_code'];
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $eidSampleCode,
                    'uniqueId' => $eidData['unique_id'],
                    'appSampleCode' => $eidData['app_sample_code'],
                );
            } else {
                $responseData[$rootKey] = array(
                    'sampleCode' => $eidSampleCode,
                    'uniqueId' => $eidData['unique_id'],
                    'appSampleCode' => $eidData['app_sample_code'],
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
                    'error' => 'Unable to add this EID sample. Please try again later',
                    'data' => array()
                );
            }
            http_response_code(301);
        }
    }
    $app = new \Vlsm\Models\App();
    $trackId = $app->addApiTracking($user['user_id'], count($input['data']), 'add-request', 'eid', $requestUrl, $params, 'json');
    if ($update == "yes") {
        $msg = 'Successfully updated.';
    } else {
        $msg = 'Successfully added.';
    }
    if (isset($responseData) && count($responseData) > 0) {
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

    if (isset($user['token_updated']) && $user['token_updated'] == true) {
        $payload['token'] = $user['new_token'];
    } else {
        $payload['token'] = null;
    }
    http_response_code(200);
    echo json_encode($payload);
    exit(0);
} catch (Exception $exc) {

    http_response_code(500);
    $payload = array(
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => array()
    );


    echo json_encode($payload);

    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    exit(0);
}
