<?php

namespace App\Services;

use Exception;
use SAMPLE_STATUS;
use COUNTRY;
use App\Utilities\DateUtility;
use App\Abstracts\AbstractTestService;


class GenericTestsService extends AbstractTestService
{

    protected string $table = 'form_generic';
    protected string $shortCode = 'T';

    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            return json_encode([]);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] = $params['testType'] ?? $this->shortCode;
            return $this->generateSampleCode($this->table, $params);
        }
    }

    public function getGenericSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_type_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_generic_sample_types where sample_type_status='active '$where";
        return $this->db->rawQuery($query);
    }

    public function getGenericSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_generic_sample_types where sample_type_status='active'";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_type_id']] = $row['sample_type_name'];
        }
        return $response;
    }

    public function getGenericResults(): array
    {
        return array(
            'positive' => 'Positive',
            'negative' => 'Negative',
            'invalid' => 'Invalid'
        );
    }

    public function insertSample($params, $returnSampleData = false)
    {
        try {

            $formId = $this->commonService->getGlobalConfig('vl_form');

            $testType = $params['testType'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            // Test Type cannot be empty
            if (
                empty($testType) ||
                empty($sampleCollectionDate) ||
                ($formId == COUNTRY\PNG && empty($provinceId))
            ) {
                return 0;
            }

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
            $sampleCodeParams['provinceCode'] = $params['provinceCode'] ?? null;
            $sampleCodeParams['provinceId'] = $provinceId;
            $sampleCodeParams['testType'] = $testType;
            $sampleCodeParams['maxCodeKeyVal'] = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->getSampleCode($sampleCodeParams);
            $sampleData = json_decode($sampleJson, true);

            $sQuery = "SELECT sample_id FROM form_generic ";
            if (!empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);
            $id = 0;

            if (empty($rowData) && !empty($sampleData['sampleCode'])) {

                $tesRequestData = [
                    'vlsm_country_id' => $formId,
                    'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                    'facility_id' => $params['facilityId'] ?? null,
                    'lab_id' => $params['labId'] ?? null,
                    'app_sample_code' => $params['appSampleCode'] ?? null,
                    'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                    'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                    'province_id' => $provinceId,
                    'test_type' => $testType,
                    'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'form_attributes' => $params['formAttributes'] ?? "{}",
                    'request_created_datetime' => DateUtility::getCurrentDateTime(),
                    'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime()
                ];

                $accessType = $_SESSION['accessType'] ?? $params['accessType'] ?? null;
                $instanceType = $_SESSION['instanceType'] ?? $params['instanceType'] ?? null;

                if ($instanceType === 'remoteuser') {
                    $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'yes';
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                    if ($accessType === 'testing-lab') {
                        $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                        $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                    }
                } else {
                    $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'no';
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                }

                $formAttributes = [
                    'applicationVersion' => $this->commonService->getSystemConfig('sc_version'),
                    'ip_address' => $this->commonService->getClientIpAddress()
                ];

                $tesRequestData['form_attributes'] = json_encode($formAttributes);
                $this->db->insert("form_generic", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                    error_log($this->db->getLastQuery());
                }
            } else {
                // If this sample id exists, let us regenerate the sample id and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSample($params);
            }
        } catch (Exception $e) {
            error_log('Insert lab tests Sample : ' . $this->db->getLastErrno());
            error_log('Insert lab tests Sample : ' . $this->db->getLastError());
            error_log('Insert lab tests Sample : ' . $this->db->getLastQuery());
            error_log('Insert lab tests Sample : ' . $e->getMessage());
            $id = 0;
        }
        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $tesRequestData['unique_id'] ?? null,
                'sampleCode' => $tesRequestData['sample_code'] ?? null,
                'remoteSampleCode' => $tesRequestData['remote_sample_code'] ?? null
            ];
        } else {
            return max($id, 0);
        }
    }

    public function getDynamicFields($genericTestId): array
    {
        $return = [];
        if ($genericTestId > 0) {
            $labelsResponse = $dynamicJson = [];
            $this->db->where("sample_id", $genericTestId);
            $generic = $this->db->getOne('form_generic');
            if ($generic['test_type_form']) {
                $dynamicJson = (array) json_decode($generic['test_type_form']);
                $this->db->where('test_type_id', $generic['test_type']);
                $testTypes = $this->db->getOne('r_test_types');
                $labels = json_decode($testTypes['test_form_config'], true);

                foreach ($labels['field_id'] as $key => $le) {
                    $labelsResponse[$le] = $labels['field_name'][$key];
                }
            }
            $return = array('dynamicValue' => $dynamicJson, 'dynamicLabel' => $labelsResponse);
        }
        return $return;
    }

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('test_failure_reason_status', 'active');
        if ($updatedDateTime) {
            $this->db->where('updated_datetime >= "' . $updatedDateTime . '"');
        }
        $results = $this->db->get('r_generic_test_failure_reasons');
        if ($option) {
            foreach ($results as $row) {
                $result[$row['test_failure_reason_id']] = $row['test_failure_reason'];
            }
            return $result;
        } else {
            return $results;
        }
    }

    public function getInterpretationResults($testType, $result)
    {
        if (empty($result) || empty($testType)) {
            return null;
        }

        $this->db->where('test_type_id', $testType);
        $testTypeResult = $this->db->getOne('r_test_types');

        if (empty($testTypeResult['test_results_config'])) {
            return null;
        }

        $resultConfig = json_decode($testTypeResult['test_results_config'], true);
        $return = null;
        if (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'quantitative') {
            if (is_numeric($result)) {
                if ($result >= $resultConfig['high_value']) {
                    $return = $resultConfig['above_threshold'];
                }
                if ($result == $resultConfig['threshold_value']) {
                    $return = $resultConfig['at_threshold'];
                }
                if ($result < $resultConfig['low_value']) {
                    $return = $resultConfig['below_threshold'];
                }
            } else {
                $resultIndex = (isset($result) && isset($resultConfig['quantitative_result']) && in_array($result, $resultConfig['quantitative_result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['quantitative_result'])) : '';
                $return = $resultConfig['quantitative_result_interpretation'][$resultIndex];
            }
        } elseif (isset($resultConfig['result_type']) && $resultConfig['result_type'] == 'qualitative') {
            $resultIndex = (isset($result) && isset($resultConfig['result']) && in_array($result, $resultConfig['result'])) ? array_search(strtolower($result), array_map('strtolower', $resultConfig['result'])) : '';
            $return = $resultConfig['result_interpretation'][$resultIndex];
        }

        return $return;
    }

    public function getTestsByGenericSampleIds($genericSampleIds = []): ?array
    {
        $response = [];

        if (!empty($genericSampleIds) && is_array($genericSampleIds)) {
            $placeholders = implode(',', array_fill(0, count($genericSampleIds), '?'));
            $results = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            WHERE `generic_id` IN ($placeholders)
                                            ORDER BY test_id ASC", $genericSampleIds);
            foreach ($results as $row) {
                $response[$row['generic_id']][$row['test_id']] = $row;
            }
        } elseif (!empty($genericSampleIds) && !is_array($genericSampleIds)) {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            WHERE `generic_id` = ?
                                            ORDER BY test_id ASC", [$genericSampleIds]);
        } else {
            $response = $this->db->rawQuery("SELECT * FROM generic_test_results
                                            ORDER BY test_id ASC");
        }

        return $response;
    }


    // Quickly insert data in dynamic
    public function quickInsert($table, $fields, $values)
    {
        return $this->db->insert($table, array_combine($fields, $values));
    }

    public function getSampleType($testTypeId)
    {
        $sampleTypeQry = "SELECT *
                            FROM r_generic_sample_types as st
                            INNER JOIN generic_test_sample_type_map as map ON map.sample_type_id=st.sample_type_id
                            WHERE map.test_type_id=$testTypeId
                            AND st.sample_type_status='active'";
        return $this->db->query($sampleTypeQry);
    }

    public function getTestReason($testTypeId)
    {
        $testReasonQry = "SELECT *
                            FROM r_generic_test_reasons as tr
                            INNER JOIN generic_test_reason_map as map ON map.test_reason_id=tr.test_reason_id
                            WHERE map.test_type_id=$testTypeId
                            AND tr.test_reason_status='active'";
        return $this->db->query($testReasonQry);
    }

    public function getTestMethod($testTypeId)
    {
        $testMethodQry = "SELECT *
                            FROM r_generic_test_methods as tm
                            INNER JOIN generic_test_methods_map as map ON map.test_method_id=tm.test_method_id
                            WHERE map.test_type_id=$testTypeId
                            AND tm.test_method_status='active'";
        return $this->db->query($testMethodQry);
    }

    public function getTestResultUnit($testTypeId)
    {
        $testResultUnitQry = "SELECT *
                                FROM r_generic_test_result_units as tu
                                INNER JOIN generic_test_result_units_map as map ON map.unit_id=tu.unit_id
                                WHERE map.test_type_id=$testTypeId
                                AND tu.unit_status='active'";
        return $this->db->query($testResultUnitQry);
    }

    public function fetchRelaventDataUsingTestAttributeId($fcode)
    {
        if (!empty($fcode)) {
            // First get the collection of fcode from the following fcode
            $this->db->where("(JSON_SEARCH(test_form_config, 'one', '$fcode') IS NOT NULL) OR (test_form_config IS NOT NULL)");

            $this->db->orderBy('updated_datetime');
            $testTypeResult = $this->db->getOne('r_test_types', 'test_form_config');
            $testType = json_decode($testTypeResult['test_form_config'], true);
            $fcodes = [];
            if (isset($testType) && !empty($testType)) {
                foreach ($testType as $section => $sectionArray) {
                    foreach ($sectionArray as $key => $value) {
                        if ($value['field_code'] == $fcode) {
                            $fcodes[] = $key;
                        }
                    }
                }
            }
            // print_r($fcodes);echo "<br>";
            // After that we get the list of available values from following fcodes
            if (isset($fcodes) && count($fcodes) > 0) {
                foreach ($fcodes as $value) {
                    $this->db->where("(JSON_SEARCH(test_type_form, 'all', '$value') IS NOT NULL) OR (test_type_form IS NOT NULL)");
                }
                $this->db->orderBy('last_modified_datetime');
                $result =  $this->db->getOne('form_generic', 'test_type_form');
                if ($result) {
                    $response = [];
                    foreach ((array) json_decode($result['test_type_form']) as $key => $value) {
                        if (in_array($key, $fcodes)) {
                            $response[] = $value;
                        }
                        // print_r($key);echo "<br>";
                    }
                    // print_r($response);
                    return $response[0];
                } else {
                    return null;
                }
            }

            return null;
        } else {
            return null;
        }
    }
}
