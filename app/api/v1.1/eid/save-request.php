<?php

use App\Services\ApiService;
use App\Services\EidService;
use App\Services\CommonService;
use App\Services\UserService;
use App\Utilities\DateUtils;

session_unset(); // no need of session in json response
ini_set('memory_limit', -1);

$db = \MysqliDb::getInstance();

try {

    $general = new CommonService();
    $userDb = new UserService();
    $app = new ApiService();
    $eidModel = new EidService();
    $transactionId = $general->generateUUID();
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();
    $user = null;

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

    $sQuery = "SELECT vlsm_instance_id FROM s_vlsm_instance";
    $rowData = $db->rawQuery($sQuery);
    $instanceId = $rowData[0]['vlsm_instance_id'];
    $formId = $general->getGlobalConfig('vl_form');

    /* Update form attributes */
    $version = $general->getSystemConfig('sc_version');
    $deviceId = $general->getHeader('deviceId');

    $responseData = [];
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
        if (!is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (!is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }

        $data['api'] = "yes";

        $provinceCode = (isset($data['provinceCode']) && !empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (isset($data['provinceId']) && !empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = $data['sampleCollectionDate'] = (isset($data['sampleCollectionDate']) && !empty($data['sampleCollectionDate'])) ? $data['sampleCollectionDate'] : null;

        if (empty($sampleCollectionDate)) {
            exit();
        }
        $update = "no";
        $rowData = false;
        $uniqueId = null;
        if (!empty($data['uniqueId']) || !empty($data['appSampleCode'])) {

            $sQuery = "SELECT eid_id, unique_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_eid ";

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
                $update = "yes";
                $uniqueId = $data['uniqueId'] = $rowData['unique_id'];
                $sampleData['sampleCode'] = (!empty($rowData['sample_code'])) ? $rowData['sample_code'] : $rowData['remote_sample_code'];
                $sampleData['sampleCodeFormat'] = (!empty($rowData['sample_code_format'])) ? $rowData['sample_code_format'] : $rowData['remote_sample_code_format'];
                $sampleData['sampleCodeKey'] = (!empty($rowData['sample_code_key'])) ? $rowData['sample_code_key'] : $rowData['remote_sample_code_key'];
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

        if (empty($uniqueId) || $uniqueId === 'undefined' || $uniqueId === 'null') {
            $uniqueId = $data['uniqueId'] = $general->generateUUID();
        }

        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $data['sampleCollectionDate'] = DateUtils::isoDateFormat($data['sampleCollectionDate'], true);
        } else {
            $data['sampleCollectionDate'] = null;
        }

        $data['instanceId'] = $data['instanceId'] ?: $instanceId;

        $eidData = array(
            'vlsm_country_id' => $data['formId'] ?: null,
            'vlsm_instance_id' => $data['instanceId'],
            'unique_id' => $uniqueId,
            'sample_collection_date' => $data['sampleCollectionDate'],
            'province_id' => $provinceId,
            'request_created_by' => $user['user_id'],
            'request_created_datetime' => (isset($data['createdOn']) && !empty($data['createdOn'])) ? DateUtils::isoDateFormat($data['createdOn'], true) : DateUtils::getCurrentDateTime(),
            'last_modified_by' => $user['user_id'],
            'last_modified_datetime' => (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? DateUtils::isoDateFormat($data['updatedOn'], true) : DateUtils::getCurrentDateTime()
        );

        if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
            $eidData['remote_sample_code'] = $sampleData['sampleCode'];
            $eidData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
            $eidData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
            $eidData['remote_sample'] = 'yes';
            if ($user['access_type'] === 'testing-lab') {
                $eidData['sample_code'] = $sampleData['sampleCode'];
            }
        } else {
            $eidData['sample_code'] = $sampleData['sampleCode'];
            $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
            $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
            $eidData['remote_sample'] = 'no';
        }

        /* Update version in form attributes */
        $version = $general->getSystemConfig('sc_version');

        $formAttributes = array(
            'applicationVersion'    => $version,
            'apiTransactionId'      => $transactionId,
            'mobileAppVersion'      => $input['appVersion'],
            'deviceId'              => $deviceId
        );
        $eidData['form_attributes'] = json_encode($formAttributes);


        $id = 0;
        if (isset($rowData) && $rowData['eid_id'] > 0) {
            if ($rowData['result_status'] != 7 && $rowData['locked'] != 'yes') {
                $db = $db->where('eid_id', $rowData['eid_id']);
                $id = $db->update("form_eid", $eidData);
            }
            $data['eidSampleId'] = $rowData['eid_id'];
        } else {
            $id = $db->insert("form_eid", $eidData);
            $data['eidSampleId'] = $id;
        }
        /* print_r($db->getLastError());
        die; */
        $tableName = "form_eid";
        $tableName1 = "activity_log";



        if (empty(trim($data['sampleCode']))) {
            $data['sampleCode'] = null;
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
            $data['approvedOn'] = DateUtils::isoDateFormat($data['approvedOn'], true);
        } else {
            $data['approvedOn'] = null;
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

        if (isset($data['rapidtestDate']) && trim($data['rapidtestDate']) != "") {
            $data['rapidtestDate'] = DateUtils::isoDateFormat($data['rapidtestDate']);
        } else {
            $data['rapidtestDate'] = null;
        }

        if (isset($data['childDob']) && trim($data['childDob']) != "") {
            $data['childDob'] = DateUtils::isoDateFormat($data['childDob']);
        } else {
            $data['childDob'] = null;
        }

        if (isset($data['mothersDob']) && trim($data['mothersDob']) != "") {
            $data['mothersDob'] = DateUtils::isoDateFormat($data['mothersDob']);
        } else {
            $data['mothersDob'] = null;
        }


        if (isset($data['motherTreatmentInitiationDate']) && trim($data['motherTreatmentInitiationDate']) != "") {
            $data['motherTreatmentInitiationDate'] = DateUtils::isoDateFormat($data['motherTreatmentInitiationDate']);
        } else {
            $data['motherTreatmentInitiationDate'] = null;
        }

        if (isset($data['previousPCRTestDate']) && trim($data['previousPCRTestDate']) != "") {
            $data['previousPCRTestDate'] = DateUtils::isoDateFormat($data['previousPCRTestDate']);
        } else {
            $data['previousPCRTestDate'] = null;
        }

        if (isset($data['motherViralLoadCopiesPerMl']) && $data['motherViralLoadCopiesPerMl'] != "") {
            $motherVlResult = $data['motherViralLoadCopiesPerMl'];
        } else if (isset($data['motherViralLoadText']) && $data['motherViralLoadText'] != "") {
            $motherVlResult = $data['motherViralLoadText'];
        } else {
            $motherVlResult = null;
        }
        if (isset($data['reviewedOn']) && trim($data['reviewedOn']) != "") {
            $data['reviewedOn'] = DateUtils::isoDateFormat($data['reviewedOn']);
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

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $data['revisedOn'] = DateUtils::isoDateFormat($data['revisedOn'], true);
        } else {
            $data['revisedOn'] = null;
        }

        $eidData = array(
            'vlsm_instance_id'                                  => $instanceId,
            'vlsm_country_id'                                   => $data['formId'],
            'unique_id'                                         => $uniqueId,
            'app_sample_code'                                   => $data['appSampleCode'] ?? null,
            'facility_id'                                       => $data['facilityId'] ?? null,
            'province_id'                                       => $data['provinceId'] ?? null,
            'lab_id'                                            => $data['labId'] ?? null,
            'implementing_partner'                              => $data['implementingPartner'] ?? null,
            'funding_source'                                    => $data['fundingSource'] ?? null,
            'mother_id'                                         => $data['mothersId'] ?? null,
            'caretaker_contact_consent'                         => $data['caretakerConsentForContact'] ?? null,
            'caretaker_phone_number'                            => $data['caretakerPhoneNumber'] ?? null,
            'caretaker_address'                                 => $data['caretakerAddress'] ?? null,
            'mother_name'                                       => (isset($data['mothersName']) && !empty($data['mothersName']) && $data['mothersName'] != 'undefined') ? $data['mothersName'] : null,
            'mother_dob'                                        => $data['mothersDob'] ?? null,
            'mother_marital_status'                             => $data['mothersMaritalStatus'] ?? null,
            'mother_treatment'                                  => isset($data['motherTreatment']) ? implode(",", $data['motherTreatment']) : null,
            'mother_treatment_other'                            => $data['motherTreatmentOther'] ?? null,
            'mother_treatment_initiation_date'                  => $data['motherTreatmentInitiationDate'] ?? null,
            'child_id'                                          => $data['childId'] ?? null,
            'child_name'                                        => $data['childName'] ?? null,
            'child_surname'                                     => $data['childSurName'] ?? null,
            'child_dob'                                         => $data['childDob'] ?? null,
            'child_gender'                                      => $data['childGender'] ?? null,
            'child_age'                                         => $data['childAge'] ?? null,
            'child_treatment'                                   => isset($data['childTreatment']) ? implode(",", $data['childTreatment']) : null,
            'child_treatment_other'                             => isset($data['childTreatmentOther']) ? implode(",", $data['childTreatmentOther']) : null,
            'mother_cd4'                                        => $data['mothercd4'] ?? null,
            'mother_vl_result'                                  => $motherVlResult,
            'mother_hiv_status'                                 => $data['mothersHIVStatus'] ?? null,
            'pcr_test_performed_before'                         => $data['pcrTestPerformedBefore'] ?? null,
            'previous_pcr_result'                               => $data['prePcrTestResult'] ?? null,
            'last_pcr_date'                                     => $data['previousPCRTestDate'] ?? null,
            'reason_for_pcr'                                    => $data['pcrTestReason'] ?? null,
            'has_infant_stopped_breastfeeding'                  => $data['hasInfantStoppedBreastfeeding'] ?? null,
            'age_breastfeeding_stopped_in_months'               => $data['ageBreastfeedingStopped'] ?? null,
            'choice_of_feeding'                                 => $data['choiceOfFeeding'] ?? null,
            'is_cotrimoxazole_being_administered_to_the_infant' => $data['isCotrimoxazoleBeingAdministered'] ?? null,
            'specimen_type'                                     => $data['specimenType'] ?? null,
            'sample_collection_date'                            => $data['sampleCollectionDate'] ?? null,
            'sample_dispatched_datetime'                        => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'                        => $data['resultDispatchedOn'],
            'sample_requestor_phone'                            => $data['sampleRequestorPhone'] ?? null,
            'sample_requestor_name'                             => $data['sampleRequestorName'] ?? null,
            'rapid_test_performed'                              => $data['rapidTestPerformed'] ?? null,
            'rapid_test_date'                                   => $data['rapidtestDate'] ?? null,
            'rapid_test_result'                                 => $data['rapidTestResult'] ?? null,
            'lab_reception_person'                              => $data['labReceptionPerson'] ?? null,
            'sample_received_at_vl_lab_datetime'                => $data['sampleReceivedDate'] ?? null,
            'eid_test_platform'                                 => $data['eidPlatform'] ?? null,
            'import_machine_name'                               => $data['machineName'] ?? null,
            'sample_tested_datetime'                            => $data['sampleTestedDateTime'] ?? null,
            'is_sample_rejected'                                => $data['isSampleRejected'] ?? null,
            'result'                                            => $data['result'] ?? null,
            'tested_by'                                         => (isset($data['testedBy']) && $data['testedBy'] != '') ? $data['testedBy'] :  $user['user_id'],
            'result_approved_by'                                => (isset($data['approvedBy']) && $data['approvedBy'] != '') ? $data['approvedBy'] :  null,
            'result_approved_datetime'                          => (isset($data['approvedOn']) && $data['approvedOn'] != '') ? $data['approvedOn'] :  null,
            'lab_tech_comments'                                 => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'result_reviewed_by'                                => (isset($data['reviewedBy']) && $data['reviewedBy'] != "") ? $data['reviewedBy'] : null,
            'result_reviewed_datetime'                          => (isset($data['reviewedOn']) && $data['reviewedOn'] != "") ? $data['reviewedOn'] : null,
            'revised_by'                                        => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                                        => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : "",
            'reason_for_changing'                               => (isset($data['reasonForEidResultChanges']) && !empty($data['reasonForEidResultChanges'])) ? $data['reasonForEidResultChanges'] : null,
            'result_status'                                     => $status,
            'data_sync'                                         => 0,
            'reason_for_sample_rejection'                       => $data['sampleRejectionReason'] ?? null,
            'rejection_on'                                      => (isset($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtils::isoDateFormat($data['rejectionDate']) : null,
            'source_of_request'                                 => $data['sourceOfRequest'] ?? "API"
        );

        if ($rowData) {
            $eidData['last_modified_datetime']  = (isset($data['updatedOn']) && !empty($data['updatedOn'])) ? DateUtils::isoDateFormat($data['updatedOn'], true) : DateUtils::getCurrentDateTime();
            $eidData['last_modified_by']  = $user['user_id'];
        } else {
            $eidData['sample_registered_at_lab']  = DateUtils::getCurrentDateTime();
            $eidData['request_created_by']  = $user['user_id'];
        }

        $eidData['request_created_by'] =  $user['user_id'];
        $eidData['last_modified_by'] =  $user['user_id'];

        /* echo "<pre>";
        print_r($eidData);
        die; */
        $id = 0;
        if (!empty($data['eidSampleId'])) {
            if ($data['result_status'] != 7 && $data['locked'] != 'yes') {
                $db = $db->where('eid_id', $data['eidSampleId']);
                $id = $db->update($tableName, $eidData);
            }
        }
        if ($id > 0) {
            $eidData = $app->getTableDataUsingId($tableName, 'eid_id', $data['eidSampleId']);
            $eidSampleCode = (isset($eidData['sample_code']) && $eidData['sample_code']) ? $eidData['sample_code'] : $eidData['remote_sample_code'];
            $responseData[$rootKey] = array(
                'status' => 'success',
                'sampleCode' => $eidSampleCode,
                'transactionId' => $transactionId,
                'uniqueId' => $eidData['unique_id'],
                'appSampleCode' => (isset($data['appSampleCode']) && $data['appSampleCode'] != "") ? $eidData['app_sample_code'] : null,
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
                    'error' => 'Unable to add this EID sample. Please try again later',
                    'data' => array()
                );
            }
            http_response_code(301);
        }
    }
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
$general->addApiTracking($transactionId, $user['user_id'], count($input['data']), 'save-request', 'eid', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
// exit(0); 
