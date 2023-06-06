<?php

use JsonMachine\Items;
use App\Services\TbService;
use App\Services\ApiService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;

session_unset(); // no need of session in json response


try {
    ini_set('memory_limit', -1);

    /** @var Slim\Psr7\Request $request */
    $request = $GLOBALS['request'];

    $origJson = $request->getBody()->getContents();


    $appVersion = null;
    try {
        $appVersion = Items::fromString($origJson, [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        $appVersion = iterator_to_array($appVersion)['appVersion'];
    } catch (PathNotFoundException $ex) {
        // handle error, perhaps log it, or set a default value
        error_log("The path '/appVersion' was not found in the JSON data.");
    }

    try {

        $input = Items::fromString($origJson, [
            'pointer' => '/data',
            'decoder' => new ExtJsonDecoder(true)
        ]);
        if (empty($input)) {
            throw new PathNotFoundException();
        }
    } catch (PathNotFoundException $ex) {
        throw new SystemException("Invalid request");
    }

    /** @var MysqliDb $db */
    $db = ContainerRegistry::get('db');

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var ApiService $app */
    $app = ContainerRegistry::get(ApiService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    /** @var TbService $tbService */
    $tbService = ContainerRegistry::get(TbService::class);

    $user = null;
    $tableName = "form_tb";
    $tableName1 = "activity_log";
    $testTableName = 'tb_tests';
    $globalConfig = $general->getGlobalConfig();
    $vlsmSystemConfig = $general->getSystemConfig();

    /* For API Tracking params */
    $requestUrl = $_SERVER['HTTP_HOST'];
    $requestUrl .= $_SERVER['REQUEST_URI'];
    $authToken = $general->getAuthorizationBearerToken();
    $user = $usersService->getUserByToken($authToken);
    $roleUser = $usersService->getUserRole($user['user_id']);
    $responseData = [];

    $instanceId = $general->getInstanceId();
    $formId = $general->getGlobalConfig('vl_form');

    /* Update form attributes */
    $transactionId = $general->generateUUID();
    $version = $general->getSystemConfig('sc_version');
    $deviceId = $general->getHeader('deviceId');

    foreach ($input as $rootKey => $data) {

        $mandatoryFields = ['sampleCollectionDate', 'facilityId', 'appSampleCode'];

        if ($formId == 5) {
            $mandatoryFields[] = 'provinceId';
        }

        if ($app->checkIfNullOrEmpty(array_intersect_key($data, array_flip($mandatoryFields)))) {
            $responseData[$rootKey] = array(
                'transactionId' => $transactionId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'status' => 'failed',
                'message' => _("Missing required fields")
            );
            continue;
        }


        if (!empty($data['provinceId']) && !is_numeric($data['provinceId'])) {
            $province = explode("##", $data['provinceId']);
            if (!empty($province)) {
                $data['provinceId'] = $province[0];
            }
            $data['provinceId'] = $general->getValueByName($data['provinceId'], 'geo_name', 'geographical_divisions', 'geo_id', true);
        }
        if (isset($data['implementingPartner']) && !is_numeric($data['implementingPartner'])) {
            $data['implementingPartner'] = $general->getValueByName($data['implementingPartner'], 'i_partner_name', 'r_implementation_partners', 'i_partner_id');
        }
        if (isset($data['fundingSource']) && !is_numeric($data['fundingSource'])) {
            $data['fundingSource'] = $general->getValueByName($data['fundingSource'], 'funding_source_name', 'r_funding_sources', 'funding_source_id');
        }

        $data['api'] = "yes";
        $provinceCode = (!empty($data['provinceCode'])) ? $data['provinceCode'] : null;
        $provinceId = (!empty($data['provinceId'])) ? $data['provinceId'] : null;
        $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);

        $update = "no";
        $rowData = null;
        $uniqueId = null;
        if (!empty($data['uniqueId']) || !empty($data['appSampleCode'])) {
            $sQuery = "SELECT tb_id,
            unique_id,
            sample_code,
            sample_code_format,
            sample_code_key,
            remote_sample_code,
            remote_sample_code_format,
            remote_sample_code_key,
            result_status,
            locked
            FROM form_tb ";
            $sQueryWhere = [];

            if (!empty($data['uniqueId'])) {
                $uniqueId = $data['uniqueId'];
                $sQueryWhere[] = " unique_id like '" . $data['uniqueId'] . "'";
            }
            if (!empty($data['appSampleCode'])) {
                $sQueryWhere[] = " app_sample_code like '" . $data['appSampleCode'] . "'";
            }

            if (!empty($sQueryWhere)) {
                $sQuery .= " WHERE " . implode(" AND ", $sQueryWhere);
            }

            $rowData = $db->rawQueryOne($sQuery);

            if (!empty($rowData)) {
                if ($rowData['result_status'] == 7 || $rowData['locked'] == 'yes') {
                    $responseData[$rootKey] = array(
                        'transactionId' => $transactionId,
                        'appSampleCode' => $data['appSampleCode'] ?? null,
                        'status' => 'failed',
                        'error' => _("Sample Locked or Finalized")

                    );
                    continue;
                }
                $update = "yes";
                $uniqueId = $data['uniqueId'] = $rowData['unique_id'];
            }
        }

        if (empty($uniqueId) || $uniqueId === 'undefined' || $uniqueId === 'null') {
            $uniqueId = $data['uniqueId'] = $general->generateUUID();
        }

        $formAttributes = array(
            'applicationVersion'    => $version,
            'apiTransactionId'      => $transactionId,
            'mobileAppVersion'      => $appVersion,
            'deviceId'              => $deviceId
        );
        $formAttributes = json_encode($formAttributes);


        if (!empty($rowData)) {
            $data['tbSampleId'] = $rowData['tb_id'];
        } else {
            $params['appSampleCode'] = $data['appSampleCode'] ?? null;
            $params['provinceCode'] = $provinceCode;
            $params['provinceId'] = $provinceId;
            $params['uniqueId'] = $uniqueId;
            $params['sampleCollectionDate'] = $sampleCollectionDate;
            $params['userId'] = $user['user_id'];
            $params['facilityId'] = $data['facilityId'] ?? null;
            $params['labId'] = $data['labId'] ?? null;

            $data['tbSampleId'] = $tbService->insertSampleCode($params);
        }

        $status = 6;
        if ($roleUser['access_type'] != 'testing-lab') {
            $status = 9;
        }

        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = null;
        }
        if (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "yes") {
            $data['result'] = null;
            $status = 4;
        } elseif (
            isset($globalConfig['tb_auto_approve_api_results']) &&
            $globalConfig['tb_auto_approve_api_results'] == "yes" &&
            (isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") &&
            (!empty($data['result']))
        ) {
            $status = 7;
        } elseif ((isset($data['isSampleRejected']) && $data['isSampleRejected'] == "no") && (!empty($data['result']))) {
            $status = 8;
        }

        if (!empty($data['sampleCollectionDate']) && trim($data['sampleCollectionDate']) != "") {
            $sampleCollectionDate = $data['sampleCollectionDate'] = DateUtility::isoDateFormat($data['sampleCollectionDate'], true);
        } else {
            $sampleCollectionDate = $data['sampleCollectionDate'] = null;
        }


        //Set sample received date
        if (!empty($data['sampleReceivedDate']) && trim($data['sampleReceivedDate']) != "") {
            $sampleReceivedDate = explode(" ", $data['sampleReceivedDate']);
            $data['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
        } else {
            $data['sampleReceivedDate'] = null;
        }

        if (!empty($data['sampleReceivedHubDate']) && trim($data['sampleReceivedHubDate']) != "") {
            $sampleReceivedHubDate = explode(" ", $data['sampleReceivedHubDate']);
            $data['sampleReceivedHubDate'] = DateUtility::isoDateFormat($sampleReceivedHubDate[0]) . " " . $sampleReceivedHubDate[1];
        } else {
            $data['sampleReceivedHubDate'] = null;
        }
        if (!empty($data['sampleTestedDateTime']) && trim($data['sampleTestedDateTime']) != "") {
            $sampleTestedDate = explode(" ", $data['sampleTestedDateTime']);
            $data['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
        } else {
            $data['sampleTestedDateTime'] = null;
        }

        if (!empty($data['arrivalDateTime']) && trim($data['arrivalDateTime']) != "") {
            $arrivalDate = explode(" ", $data['arrivalDateTime']);
            $data['arrivalDateTime'] = DateUtility::isoDateFormat($arrivalDate[0]) . " " . $arrivalDate[1];
        } else {
            $data['arrivalDateTime'] = null;
        }

        if (!empty($data['revisedOn']) && trim($data['revisedOn']) != "") {
            $revisedOn = explode(" ", $data['revisedOn']);
            $data['revisedOn'] = DateUtility::isoDateFormat($revisedOn[0]) . " " . $revisedOn[1];
        } else {
            $data['revisedOn'] = null;
        }

        if (isset($data['resultDispatchedOn']) && trim($data['resultDispatchedOn']) != "") {
            $resultDispatchedOn = explode(" ", $data['resultDispatchedOn']);
            $data['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
        } else {
            $data['resultDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedOn']) && trim($data['sampleDispatchedOn']) != "") {
            $sampleDispatchedOn = explode(" ", $data['sampleDispatchedOn']);
            $data['sampleDispatchedOn'] = DateUtility::isoDateFormat($sampleDispatchedOn[0]) . " " . $sampleDispatchedOn[1];
        } else {
            $data['sampleDispatchedOn'] = null;
        }

        if (isset($data['sampleDispatchedDate']) && trim($data['sampleDispatchedDate']) != "") {
            $sampleDispatchedDate = explode(" ", $data['sampleDispatchedDate']);
            $data['sampleDispatchedDate'] = DateUtility::isoDateFormat($sampleDispatchedDate[0]) . " " . $sampleDispatchedDate[1];
        } else {
            $data['sampleDispatchedDate'] = null;
        }

        $tbData = array(
            'vlsm_instance_id'                    => $data['instanceId'],
            'vlsm_country_id'                     => $formId,
            'unique_id'                           => $uniqueId,
            'app_sample_code'                     => !empty($data['appSampleCode']) ? $data['appSampleCode'] : null,
            'sample_reordered'                    => !empty($data['sampleReordered']) ? $data['sampleReordered'] : 'no',
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
            'patient_dob'                         => !empty($data['patientDob']) ? DateUtility::isoDateFormat($data['patientDob']) : null,
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
            'sample_collection_date'              => $data['sampleCollectionDate'],
            'sample_dispatched_datetime'          => $data['sampleDispatchedOn'],
            'result_dispatched_datetime'          => $data['resultDispatchedOn'],
            'sample_tested_datetime'              => $data['sampleTestedDateTime'] ?? null,
            'sample_received_at_hub_datetime'     => !empty($data['sampleReceivedHubDate']) ? $data['sampleReceivedHubDate'] : null,
            'sample_received_at_lab_datetime'     => !empty($data['sampleReceivedDate']) ? $data['sampleReceivedDate'] : null,
            'lab_technician'                      => (!empty($data['labTechnician']) && $data['labTechnician'] != '') ? $data['labTechnician'] :  $user['user_id'],
            'lab_reception_person'                => (!empty($data['labReceptionPerson']) && $data['labReceptionPerson'] != '') ? $data['labReceptionPerson'] :  null,
            'is_sample_rejected'                  => !empty($data['isSampleRejected']) ? $data['isSampleRejected'] : null,
            'result'                              => !empty($data['result']) ? $data['result'] : null,
            'xpert_mtb_result'                    => !empty($data['xpertMtbResult']) ? $data['xpertMtbResult'] : null,
            'tested_by'                           => !empty($data['testedBy']) ? $data['testedBy'] : null,
            'result_reviewed_by'                  => !empty($data['reviewedBy']) ? $data['reviewedBy'] : null,
            'result_reviewed_datetime'            => !empty($data['reviewedOn']) ? DateUtility::isoDateFormat($data['reviewedOn']) : null,
            'result_approved_by'                  => !empty($data['approvedBy']) ? $data['approvedBy'] : null,
            'result_approved_datetime'            => !empty($data['approvedOn']) ? DateUtility::isoDateFormat($data['approvedOn']) : null,
            'lab_tech_comments'                   => !empty($data['approverComments']) ? $data['approverComments'] : null,
            'revised_by'                          => (isset($data['revisedBy']) && $data['revisedBy'] != "") ? $data['revisedBy'] : "",
            'revised_on'                          => (isset($data['revisedOn']) && $data['revisedOn'] != "") ? $data['revisedOn'] : null,
            'reason_for_changing'                 => (!empty($data['reasonFortbResultChanges'])) ? $data['reasonFortbResultChanges'] : null,
            'rejection_on'                        => (!empty($data['rejectionDate']) && $data['isSampleRejected'] == 'yes') ? DateUtility::isoDateFormat($data['rejectionDate']) : null,
            'result_status'                       => $status,
            'data_sync'                           => 0,
            'reason_for_sample_rejection'         => (isset($data['sampleRejectionReason']) && $data['isSampleRejected'] == 'yes') ? $data['sampleRejectionReason'] : null,
            'source_of_request'                   => $data['sourceOfRequest'] ?? "API",
            'form_attributes'                       => $db->func($general->jsonToSetString($formAttributes, 'form_attributes'))
        );
        if (!empty($rowData)) {
            $tbData['last_modified_datetime']  = (!empty($data['updatedOn'])) ? DateUtility::isoDateFormat($data['updatedOn'], true) : DateUtility::getCurrentDateTime();
            $tbData['last_modified_by']  = $user['user_id'];
        } else {
            $tbData['request_created_datetime']  = (!empty($data['createdOn'])) ? DateUtility::isoDateFormat($data['createdOn'], true) : DateUtility::getCurrentDateTime();
            $tbData['sample_registered_at_lab']  = DateUtility::getCurrentDateTime();
            $tbData['request_created_by']  = $user['user_id'];
        }

        $tbData['request_created_by'] =  $user['user_id'];
        $tbData['last_modified_by'] =  $user['user_id'];

        if (isset($data['tbSampleId']) && $data['tbSampleId'] != '' && ($data['isSampleRejected'] == 'no' || $data['isSampleRejected'] == '')) {
            if (!empty($data['testResult'])) {
                $db = $db->where('tb_id', $data['tbSampleId']);
                $db->delete($testTableName);

                foreach ($data['testResult'] as $testKey => $testResult) {
                    if (!empty($testResult) && trim($testResult) != "") {
                        $db->insert($testTableName, array(
                            'tb_id'             => $data['tbSampleId'],
                            'actual_no'         => $data['actualNo'][$testKey] ?? null,
                            'test_result'       => $testResult,
                            'updated_datetime'  => DateUtility::getCurrentDateTime()
                        ));
                    }
                }
            }
        } else {
            $db = $db->where('tb_id', $data['tbSampleId']);
            $db->delete($testTableName);
        }
        $id = false;
        if (!empty($data['tbSampleId'])) {
            $db = $db->where('tb_id', $data['tbSampleId']);
            $id = $db->update($tableName, $tbData);
        }
        if ($id === true) {
            $sQuery = "SELECT sample_code,
                        remote_sample_code
                        FROM form_tb
                        WHERE tb_id = ?";
            $sampleRow = $db->rawQueryOne($sQuery, [$data['tbSampleId']]);

            $tbSampleCode = $sampleRow['sample_code'] ?? $sampleRow['remote_sample_code'] ?? null;
            $responseData[$rootKey] = [
                'status' => 'success',
                'sampleCode' => $tbSampleCode,
                'transactionId' => $transactionId,
                'uniqueId' => $uniqueId,
                'appSampleCode' => $data['appSampleCode'] ?? null,
            ];
            http_response_code(200);
        } else {
            $responseData[$rootKey] = [
                'transactionId' => $transactionId,
                'status' => 'failed',
                'appSampleCode' => $data['appSampleCode'] ?? null,
                'error' => $db->getLastError()
            ];
        }
    }

    $payload = [
        'status' => 'success',
        'transactionId' => $transactionId,
        'timestamp' => time(),
        'data'  => $responseData ?? []
    ];
    http_response_code(200);
} catch (SystemException $exc) {

    http_response_code(500);
    $payload = [
        'status' => 'failed',
        'timestamp' => time(),
        'error' => $exc->getMessage(),
        'data' => []
    ];
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
$payload = json_encode($payload);
$general->addApiTracking($transactionId, $user['user_id'], iterator_count($input), 'save-request', 'tb', $_SERVER['REQUEST_URI'], $origJson, $payload, 'json');
echo $payload;
