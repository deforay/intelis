<?php

session_unset(); // no need of session in json response
try {
    ini_set('memory_limit', -1);
    header('Content-Type: application/json');
    $general = new \Vlsm\Models\General();
    $userDb = new \Vlsm\Models\Users();
    $app = new \Vlsm\Models\App();
    $tbModel = new \Vlsm\Models\Tb();

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
        $formId = $general->getGlobalConfig('vl_form');
        if ($data['formId'] == $formId) {
        }
        $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
        $rowData = $db->rawQuery($sQuery);
        $data['instanceId'] = $rowData[0]['vlsm_instance_id'];
        $sampleFrom = '';

        /* Checkng required fields */
        if (empty($data['uniqueId']) || empty($data['appSampleCode']) || empty($data['facilityId']) || empty($data['patientId']) || empty($data['reasonFortbTest']) || empty($data['specimenType']) || empty($data['sampleCollectionDate'])) {
            throw new Exception("Invalid request. Please check your request parameters.");
            exit(0);
        }

        $generatedUniqueId = null;
        $existingData = $db->rawQuery('SELECT unique_id, app_sample_code FROM form_tb WHERE app_sample_code = ? OR unique_id = ? OR sample_code = ? OR remote_sample_code = ?', array($data['appSampleCode'], $data['uniqueId'], $data['sampleCode'], $data['remoteSampleCode']));

        // FOR EXISTING SAMPLES:
        if (count($existingData) > 0) {
            // check if API provided unique_id is matching with the one in our db
            if (array_search($data['uniqueId'], array_column($existingData, 'unique_id'))) {
                throw new Exception("Invalid request. Please check your request parameters.");
                exit(0);
            }
            if (array_search($data['appSampleCode'], array_column($existingData, 'app_sample_code'))) {
                throw new Exception("Invalid request. Please check your request parameters.");
                exit(0);
            }
        }
        // FOR NEW SAMPLES
        else {
            if (empty($data['uniqueId'])) {
                $generatedUniqueId = $app->generateUniqueId("form_tb", "unique_id");
            }
        }


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
            $sQuery = "SELECT tb_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_tb ";
            if (isset($data['uniqueId']) && !empty($data['uniqueId'])) {
                $sQuery .= "where unique_id like '" . $data['uniqueId'] . "'";
            } else if (isset($data['sampleCode']) && !empty($data['sampleCode'])) {
                $sQuery .= "where sample_code like '" . $data['sampleCode'] . "'";
            } else if (isset($data['remoteSampleCode']) != "" && !empty($data['remoteSampleCode'])) {
                $sQuery .= "where remote_sample_code like '" . $data['sampleCode'] . "'";
            }
            $sQuery .= "limit 1";
            $rowData = $db->rawQueryOne($sQuery);
            if ($rowData) {
                $update = "yes";
                $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
                $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
                $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
            } else {
                $sampleJson = $tbModel->generateTbSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
                $sampleData = json_decode($sampleJson, true);
            }
        } else {
            $sampleJson = $tbModel->generateTbSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, null, $user);
            $sampleData = json_decode($sampleJson, true);
        }

        if (!isset($data['formId']) || $data['formId'] == '') {
            $data['formId'] = '';
        }
        $tbData = array(
            'vlsm_country_id' => $data['formId'],
            'sample_collection_date' => $data['sampleCollectionDate'],
            'vlsm_instance_id' => $data['instanceId'],
            'province_id' => $provinceId,
            'request_created_by' => null,
            'request_created_datetime' => $general->getDateTime(),
            'last_modified_by' => null,
            'last_modified_datetime' => $general->getDateTime()
        );

        if ($user['access_type'] != 'testing-lab') {
            $tbData['remote_sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $tbData['remote_sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $tbData['remote_sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $tbData['remote_sample'] = 'yes';
            $tbData['result_status'] = 9;
            /* if ($roleUser['access_type'] == 'testing-lab') {
                $tbData['sample_code'] = !empty($data['appSampleCode']) ? $data['appSampleCode'] : null;
            } */
        } else {
            $tbData['sample_code'] = (isset($sampleData['sampleCode']) && $sampleData['sampleCode'] != "") ? $sampleData['sampleCode'] : null;
            $tbData['sample_code_format'] = (isset($sampleData['sampleCodeFormat']) && $sampleData['sampleCodeFormat'] != "") ? $sampleData['sampleCodeFormat'] : null;
            $tbData['sample_code_key'] = (isset($sampleData['sampleCodeKey']) && $sampleData['sampleCodeKey'] != "") ? $sampleData['sampleCodeKey'] : null;
            $tbData['remote_sample'] = 'no';
            $tbData['result_status'] = 6;
        }
        $id = 0;
        if ($rowData) {
            $db = $db->where('tb_id', $rowData['tb_id']);
            $id = $db->update("form_tb", $tbData);
            $data['tbSampleId'] = $rowData['tb_id'];
        } else {
            $id = $db->insert("form_tb", $tbData);
            $data['tbSampleId'] = $id;
        }
        $tableName = "form_tb";
        $tableName1 = "activity_log";
        $testTableName = 'tb_tests';

        $instanceId = '';
        if (empty($instanceId) && $data['instanceId']) {
            $instanceId = $data['instanceId'];
        }
        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = $general->dateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = NULL;
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
            isset($globalConfig['tb_auto_approve_api_results']) &&
            $globalConfig['tb_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (isset($data['result']) && !empty($data['result']))
        ) {
            $status = 7;
        } else if ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (isset($data['result']) && !empty($data['result']))) {
            $status = 8;
        }

        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $sampleCollectionDate = explode(" ", $data['sampleCollectionDate']);
            $data['sampleCollectionDate'] = $general->dateFormat($sampleCollectionDate[0]) . " " . $sampleCollectionDate[1];
        } else {
            $data['sampleCollectionDate'] = NULL;
        }

        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $sampleReceivedDate = explode(" ", $data['sampleReceivedDate']);
            $data['sampleReceivedDate'] = $general->dateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
        } else {
            $data['sampleReceivedDate'] = NULL;
        }

        if (!empty($data['sampleReceivedHubDate']) && trim($data['sampleReceivedHubDate']) != "") {
            $sampleReceivedHubDate = explode(" ", $data['sampleReceivedHubDate']);
            $data['sampleReceivedHubDate'] = $general->dateFormat($sampleReceivedHubDate[0]) . " " . $sampleReceivedHubDate[1];
        } else {
            $data['sampleReceivedHubDate'] = NULL;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $sampleTestedDate = explode(" ", $data['sampleTestedDateTime']);
            $data['sampleTestedDateTime'] = $general->dateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
        } else {
            $data['sampleTestedDateTime'] = NULL;
        }

        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = $general->dateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = NULL;
        }

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $revisedOn = explode(" ", $data['revisedOn']);
            $data['revisedOn'] = $general->dateFormat($revisedOn[0]) . " " . $revisedOn[1];
        } else {
            $data['revisedOn'] = NULL;
        }

        if (isset($data['resultDispatchedOn']) && trim($data['resultDispatchedOn']) != "") {
            $resultDispatchedOn = explode(" ", $data['resultDispatchedOn']);
            $data['resultDispatchedOn'] = $general->dateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
        } else {
            $data['resultDispatchedOn'] = NULL;
        }

        if (isset($data['sampleDispatchedOn']) && trim($data['sampleDispatchedOn']) != "") {
            $sampleDispatchedOn = explode(" ", $data['sampleDispatchedOn']);
            $data['sampleDispatchedOn'] = $general->dateFormat($sampleDispatchedOn[0]) . " " . $sampleDispatchedOn[1];
        } else {
            $data['sampleDispatchedOn'] = NULL;
        }

        if (isset($data['sampleDispatchedDate']) && trim($data['sampleDispatchedDate']) != "") {
            $sampleDispatchedDate = explode(" ", $data['sampleDispatchedDate']);
            $data['sampleDispatchedDate'] = $general->dateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
        } else {
            $data['sampleDispatchedDate'] = NULL;
        }

        $tbData = array(
            'vlsm_instance_id'                    => $instanceId,
            'vlsm_country_id'                     => $data['formId'],
            'unique_id'                           => isset($data['uniqueId']) ? $data['uniqueId'] : $generatedUniqueId,
            'app_sample_code'                     => !empty($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'sample_reordered'                    => !empty($data['sampleReordered']) ? $data['sampleReordered'] : null,
            'facility_id'                         => !empty($data['facilityId']) ? $data['facilityId'] : null,
            'province_id'                         => !empty($data['provinceId']) ? $data['provinceId'] : null,
            'referring_unit'                      => !empty($data['referringUnit']) ? $data['referringUnit'] : null,
            'sample_requestor_name'               => !empty($data['sampleRequestorName']) ? $data['sampleRequestorName'] : null,
            'sample_requestor_phone'              => !empty($data['sampleRequestorPhone']) ? $data['sampleRequestorPhone'] : null,
            'specimen_quality'                    => !empty($data['specimenQuality']) ? $data['specimenQuality'] : null,
            'other_referring_unit'                => !empty($data['otherReferringUnit']) ? $data['otherReferringUnit'] : null,
            'lab_id'                              => !empty($data['labId']) ? $data['labId'] : null,
            'implementing_partner'                => !empty($data['implementingPartner']) ? $data['implementingPartner'] : null,
            'funding_source'                      => !empty($data['fundingSource']) ? $data['fundingSource'] : null,
            'patient_id'                          => !empty($data['patientId']) ? $data['patientId'] : null,
            'patient_name'                        => !empty($data['firstName']) ? $data['firstName'] : null,
            'patient_surname'                     => !empty($data['lastName']) ? $data['lastName'] : null,
            'patient_dob'                         => !empty($data['patientDob']) ? $general->dateFormat($data['patientDob']) : null,
            'patient_gender'                      => !empty($data['patientGender']) ? $data['patientGender'] : null,
            'patient_age'                         => !empty($data['patientAge']) ? $data['patientAge'] : null,
            'patient_address'                     => !empty($data['patientAddress']) ? $data['patientAddress'] : null,
            'patient_type'                        => !empty($data['patientType']) ? json_encode($data['patientType']) : null,
            'other_patient_type'                  => !empty($data['otherPatientType']) ? $data['otherPatientType'] : null,
            'hiv_status'                          => !empty($data['hivStatus']) ? $data['hivStatus'] : null,
            'reason_for_tb_test'                  => !empty($data['reasonFortbTest']) ? json_encode($data['reasonFortbTest']) : null,
            'tests_requested'                     => !empty($data['testTypeRequested']) ? json_encode($data['testTypeRequested']) : null,
            'specimen_type'                       => !empty($data['specimenType']) ? $data['specimenType'] : null,
            'other_specimen_type'                 => !empty($data['otherSpecimenType']) ? $data['otherSpecimenType'] : null,
            'sample_collection_date'              => !empty($data['sampleCollectionDate']) ? $data['sampleCollectionDate'] : null,
            'sample_dispatched_datetime'          => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'          => $data['resultDispatchedOn'],
            'sample_tested_datetime'              => isset($data['sampleTestedDateTime']) ? $data['sampleTestedDateTime'] : null,
            'sample_received_at_hub_datetime'     => !empty($data['sampleReceivedHubDate']) ? $data['sampleReceivedHubDate'] : null,
            'sample_received_at_lab_datetime'     => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
            'lab_technician'                      => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] :  $user['user_id'],
            'lab_reception_person'                => (!empty($data['labReceptionPerson']) && $data['labReceptionPerson'] != '') ? $data['labReceptionPerson'] :  null,
            'is_sample_rejected'                  => !empty($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
            'result'                              => !empty($data['result']) ? $data['result'] : null,
            'xpert_mtb_result'                    => !empty($data['xpertMtbResult']) ? $data['xpertMtbResult'] : null,
            'tested_by'                           => !empty($data['testedBy']) ? $data['testedBy'] : null,
            'result_reviewed_by'                  => !empty($data['reviewedBy']) ? $data['reviewedBy'] : null,
            'result_reviewed_datetime'            => !empty($data['reviewedOn']) ? $general->dateFormat($data['reviewedOn']) : null,
            'result_approved_by'                  => !empty($data['approvedBy']) ? $data['approvedBy'] : null,
            'result_approved_datetime'            => !empty($data['approvedOn']) ? $general->dateFormat($data['approvedOn']) : null,
            'lab_tech_comments'                   => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'revised_by'                          => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                          => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : "",
            'reason_for_changing'                 => (!empty($data['reasonFortbResultChanges']) && !empty($data['reasonFortbResultChanges'])) ? $data['reasonFortbResultChanges'] : null,
            'rejection_on'                        => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? $general->dateFormat($data['rejectionDate']) : null,
            'result_status'                       => $status,
            'data_sync'                           => 0,
            'reason_for_sample_rejection'         => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
            'source_of_request'                   => "app"
        );
        if ($rowData) {
            $tbData['last_modified_datetime']  = $general->getDateTime();
            $tbData['last_modified_by']  = $user['user_id'];
        } else {
            $tbData['request_created_datetime']  = (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : $general->getDateTime();
            $tbData['sample_registered_at_lab']  = $general->getDateTime();
            $tbData['request_created_by']  = $user['user_id'];
        }

        $tbData['request_created_by'] =  $user['user_id'];
        $tbData['last_modified_by'] =  $user['user_id'];

        if (isset($data['tbSampleId']) && $data['tbSampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (isset($data['testResult']) && count($data['testResult']) > 0) {
                $db = $db->where('tb_id', $data['tbSampleId']);
                $db->delete($testTableName);

                foreach ($data['testResult'] as $testKey => $testResult) {
                    if (isset($testResult) && !empty($testResult) && trim($testResult) != "") {
                        $db->insert($testTableName, array(
                            'tb_id'             => $data['tbSampleId'],
                            'actual_no'         => isset($data['actualNo'][$testKey]) ? $data['actualNo'][$testKey] : null,
                            'test_result'       => $testResult,
                            'updated_datetime'  => $general->getDateTime()
                        ));
                    }
                }
            }
        } else {
            $db = $db->where('tb_id', $data['tbSampleId']);
            $db->delete($testTableName);
        }
        $id = 0;
        if (!empty($data['tbSampleId'])) {
            $db = $db->where('tb_id', $data['tbSampleId']);
            $id = $db->update($tableName, $tbData);
        }
        /* echo "<pre>";
        print_r($id);
        die; */
        if ($id > 0) {
            $tbData = $app->getTableDataUsingId('form_tb', 'tb_id', $data['tbSampleId']);
            $c19SampleCode = (isset($tbData['sample_code']) && $tbData['sample_code']) ? $tbData['sample_code'] : $tbData['remote_sample_code'];
            if (isset($data['appSampleCode']) && $data['appSampleCode'] != "") {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $c19SampleCode,
                    'uniqueId' => $tbData['unique_id'],
                    'appSampleCode' => $tbData['app_sample_code'],
                );
            } else {
                $responseData[$rootKey] = array(
                    'status' => 'success',
                    'sampleCode' => $c19SampleCode,
                    'uniqueId' => $tbData['unique_id'],
                    'appSampleCode' => $tbData['app_sample_code'],
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
                    'error' => 'Unable to add this TB sample. Please try again later',
                    'data' => array()
                );
            }
            http_response_code(301);
        }
    }
    $app = new \Vlsm\Models\App();
    $trackId = $app->addApiTracking($user['user_id'], count($input['data']), 'add-request', 'tb', $requestUrl, $params, 'json');
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
